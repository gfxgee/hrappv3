<?php

namespace App\Services;

use App\Models\ZktecoAttendance;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Writes biometric punches into the SharePoint "Timekeeping" list via Microsoft
 * Graph (app-only / client credentials).
 *
 * The authority for who may appear in Timekeeping — and which email to use — is
 * the "Active Employees" workbook stored in SharePoint, mirroring the DF Portal.
 * A punch whose biometric id is not listed there is treated as not a legitimate
 * active employee and is skipped.
 */
class ZktecoTimekeepingService
{
    /** Cache key for the biometric-id → employee map built from the workbook. */
    private const EMPLOYEES_CACHE_KEY = 'zkteco_active_employees_map';

    /** Cache key for the resolved Timekeeping list id. */
    private const LIST_CACHE_KEY = 'zkteco_timekeeping_list_id';

    /** Cache key for the app-only Graph token. */
    private const TOKEN_CACHE_KEY = 'zkteco_sharepoint_token';

    /**
     * Resolve a biometric id to an active employee from the workbook.
     *
     * @return array{email: string, name: string}|null
     */
    public function findEmployeeByBiometricId(int $biometricId): ?array
    {
        return $this->activeEmployeesMap()[(string) $biometricId] ?? null;
    }

    /**
     * Clear and rebuild the cached Active Employees map. Returns the entry count
     * so the scheduled/manual refresh can report it.
     */
    public function refreshActiveEmployees(): int
    {
        Cache::forget(self::EMPLOYEES_CACHE_KEY);

        return count($this->activeEmployeesMap());
    }

    /**
     * Create one Timekeeping list item for a punch.
     *
     * @throws RuntimeException When the token, list, or item creation fails, so
     *                          the calling job can retry.
     */
    public function recordPunch(string $email, ZktecoAttendance $attendance): void
    {
        $token = $this->accessToken();

        if ($token === null) {
            throw new RuntimeException('Could not acquire a SharePoint Graph token.');
        }

        $listId = $this->timekeepingListId($token);

        if ($listId === null) {
            throw new RuntimeException('Timekeeping list could not be resolved.');
        }

        $siteId = config('services.sharepoint.site_id');
        $emailField = config('services.sharepoint.email_field', 'Class');

        $response = Http::withToken($token)
            ->timeout(10)
            ->connectTimeout(5)
            ->retry(2, 300)
            ->post("https://graph.microsoft.com/v1.0/sites/{$siteId}/lists/{$listId}/items", [
                'fields' => [
                    'Title' => $this->punchLabel($attendance->status1),
                    $emailField => $email,
                ],
            ]);

        if ($response->failed()) {
            Log::error('ZKTeco: failed to create Timekeeping entry', [
                'status' => $response->status(),
                'bio_metric_id' => $attendance->bio_metric_id,
            ]);

            throw new RuntimeException('Timekeeping entry creation failed: '.$response->status());
        }
    }

    /**
     * The cached biometric-id → employee map, rebuilt from the workbook on miss.
     *
     * @return array<string, array{email: string, name: string}>
     */
    protected function activeEmployeesMap(): array
    {
        $hours = (int) config('services.sharepoint.employees_cache_hours', 24);

        return Cache::remember(self::EMPLOYEES_CACHE_KEY, now()->addHours(max(1, $hours)), function (): array {
            return $this->buildActiveEmployeesMap();
        });
    }

    /**
     * Download the Active Employees workbook and build the biometric-id map.
     *
     * @return array<string, array{email: string, name: string}>
     */
    protected function buildActiveEmployeesMap(): array
    {
        $token = $this->accessToken();

        if ($token === null) {
            return [];
        }

        $file = $this->locateEmployeesWorkbook($token);

        if ($file === null) {
            return [];
        }

        $siteId = config('services.sharepoint.site_id');

        // Download the raw .xlsx — the workbook API needs Office Online (WAC),
        // which is unavailable with app-only client-credentials auth.
        $downloadUrl = $file['driveId']
            ? "https://graph.microsoft.com/v1.0/drives/{$file['driveId']}/items/{$file['id']}/content"
            : "https://graph.microsoft.com/v1.0/sites/{$siteId}/drive/items/{$file['id']}/content";

        $response = Http::withToken($token)->timeout(20)->connectTimeout(5)->retry(2, 300)->get($downloadUrl);

        if ($response->failed()) {
            Log::error('ZKTeco: failed to download Active Employees workbook', ['status' => $response->status()]);

            return [];
        }

        $tmpPath = tempnam(sys_get_temp_dir(), 'zkteco_ae_').'.xlsx';
        file_put_contents($tmpPath, $response->body());

        try {
            $rows = $this->parseXlsx($tmpPath);
        } catch (\Throwable $e) {
            Log::error('ZKTeco: failed to parse Active Employees workbook', ['error' => $e->getMessage()]);

            return [];
        } finally {
            @unlink($tmpPath);
        }

        return $this->mapRows($rows);
    }

    /**
     * Find the Active Employees .xlsx in the site drive.
     *
     * @return array{id: string, driveId: ?string}|null
     */
    protected function locateEmployeesWorkbook(string $token): ?array
    {
        $siteId = config('services.sharepoint.site_id');
        $search = config('services.sharepoint.employees_search', 'Active Employees');

        $response = Http::withToken($token)
            ->timeout(10)
            ->connectTimeout(5)
            ->retry(2, 300)
            ->get("https://graph.microsoft.com/v1.0/sites/{$siteId}/drive/root/search(q='".rawurlencode($search)."')", [
                '$select' => 'id,name,parentReference',
            ]);

        if ($response->failed()) {
            Log::error('ZKTeco: failed to search for Active Employees workbook', ['status' => $response->status()]);

            return null;
        }

        foreach ($response->json('value', []) as $item) {
            $name = $item['name'] ?? '';

            if (preg_match('/^active.?employees/i', $name) && str_ends_with(strtolower($name), '.xlsx')) {
                return [
                    'id' => $item['id'],
                    'driveId' => $item['parentReference']['driveId'] ?? null,
                ];
            }
        }

        Log::error('ZKTeco: Active Employees workbook not found in SharePoint');

        return null;
    }

    /**
     * Build the biometric-id → employee map from parsed workbook rows.
     *
     * @param  list<list<string>>  $rows
     * @return array<string, array{email: string, name: string}>
     */
    protected function mapRows(array $rows): array
    {
        if ($rows === []) {
            return [];
        }

        $headers = array_map('strval', $rows[0]);
        $emailIdx = array_search('Email', $headers, true);

        // "Biometic ID" (misspelled in the sheet) holds the real ZKTeco ids;
        // the internal "BioID" counter is checked last as a fallback.
        $biometricIdx = false;
        foreach (['Biometric ID', 'Biometic ID', 'BiometricID', 'Bio ID', 'BioID'] as $candidate) {
            $idx = array_search($candidate, $headers, true);
            if ($idx !== false) {
                $biometricIdx = $idx;
                break;
            }
        }

        if ($emailIdx === false || $biometricIdx === false) {
            Log::error('ZKTeco: Could not find Email or Biometric ID columns in Active Employees', ['headers' => $headers]);

            return [];
        }

        $map = [];

        foreach (array_slice($rows, 1) as $row) {
            // Cast via int first to normalise Excel floats (132.0 → "132").
            $biometricId = (string) (int) ($row[$biometricIdx] ?? '');
            $email = trim((string) ($row[$emailIdx] ?? ''));

            if ($biometricId === '0' || $biometricId === '' || $email === '') {
                continue;
            }

            $localPart = explode('@', $email)[0];
            $name = collect(explode('.', $localPart))->map(fn ($part) => ucfirst($part))->implode(' ');

            $map[$biometricId] = ['email' => $email, 'name' => $name];
        }

        return $map;
    }

    /**
     * Acquire (and cache) an app-only Graph access token.
     */
    protected function accessToken(): ?string
    {
        $cached = Cache::get(self::TOKEN_CACHE_KEY);

        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $tenant = config('services.sharepoint.tenant_id');

        $response = Http::asForm()
            ->timeout(10)
            ->connectTimeout(5)
            ->retry(2, 200)
            ->post("https://login.microsoftonline.com/{$tenant}/oauth2/v2.0/token", [
                'client_id' => config('services.sharepoint.client_id'),
                'client_secret' => config('services.sharepoint.client_secret'),
                'scope' => 'https://graph.microsoft.com/.default',
                'grant_type' => 'client_credentials',
            ]);

        if ($response->failed()) {
            Log::error('ZKTeco: failed to acquire SharePoint Graph token', ['status' => $response->status()]);

            return null;
        }

        $token = $response->json('access_token');
        $expiresIn = (int) $response->json('expires_in', 3600);

        if (is_string($token) && $token !== '') {
            Cache::put(self::TOKEN_CACHE_KEY, $token, max(60, $expiresIn - 60));

            return $token;
        }

        return null;
    }

    /**
     * Discover and cache the target list id by display name.
     */
    protected function timekeepingListId(string $token): ?string
    {
        return Cache::remember(self::LIST_CACHE_KEY, now()->addDay(), function () use ($token): ?string {
            $siteId = config('services.sharepoint.site_id');
            $listName = config('services.sharepoint.list_name', 'Timekeeping');

            $response = Http::withToken($token)
                ->timeout(10)
                ->connectTimeout(5)
                ->retry(2, 200)
                ->get("https://graph.microsoft.com/v1.0/sites/{$siteId}/lists", ['$select' => 'id,displayName']);

            if ($response->failed()) {
                Log::error('ZKTeco: failed to fetch SharePoint lists', ['status' => $response->status()]);

                return null;
            }

            foreach ($response->json('value', []) as $list) {
                if (($list['displayName'] ?? '') === $listName) {
                    return $list['id'];
                }
            }

            Log::error('ZKTeco: Timekeeping list not found in SharePoint site', ['list_name' => $listName]);

            return null;
        });
    }

    /**
     * Pure-PHP XLSX reader (ZipArchive + SimpleXML), so no extra dependency is
     * needed and the file never has to be opened by the workbook API.
     *
     * @return list<list<string>>
     */
    protected function parseXlsx(string $path): array
    {
        $zip = new \ZipArchive;

        if ($zip->open($path) !== true) {
            throw new RuntimeException("Cannot open xlsx as zip: {$path}");
        }

        $sharedStrings = [];
        $ssXml = $zip->getFromName('xl/sharedStrings.xml');

        if ($ssXml !== false) {
            $ss = new \SimpleXMLElement($ssXml);
            foreach ($ss->si as $si) {
                if (isset($si->t)) {
                    $sharedStrings[] = (string) $si->t;
                } else {
                    $text = '';
                    foreach ($si->r as $r) {
                        $text .= (string) $r->t;
                    }
                    $sharedStrings[] = $text;
                }
            }
        }

        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();

        if ($sheetXml === false) {
            throw new RuntimeException('sheet1.xml not found inside xlsx');
        }

        $xml = new \SimpleXMLElement($sheetXml);
        $rows = [];

        foreach ($xml->sheetData->row as $row) {
            $cells = [];
            $maxCol = 0;

            foreach ($row->c as $cell) {
                $colIdx = $this->xlsxColIndex((string) $cell['r']);
                $type = (string) $cell['t'];
                $value = '';

                if (isset($cell->v)) {
                    $raw = (string) $cell->v;
                    $value = ($type === 's') ? ($sharedStrings[(int) $raw] ?? '') : $raw;
                }

                $cells[$colIdx] = $value;
                $maxCol = max($maxCol, $colIdx);
            }

            $rowArr = [];
            for ($i = 0; $i <= $maxCol; $i++) {
                $rowArr[] = $cells[$i] ?? '';
            }
            $rows[] = $rowArr;
        }

        return $rows;
    }

    /**
     * Convert a cell reference like "AB12" to a 0-based column index.
     */
    protected function xlsxColIndex(string $cellRef): int
    {
        preg_match('/^([A-Z]+)/', strtoupper($cellRef), $m);
        $letters = $m[1] ?? 'A';
        $index = 0;

        foreach (str_split($letters) as $ch) {
            $index = $index * 26 + (ord($ch) - 64);
        }

        return $index - 1;
    }

    /**
     * Map the device's status byte to the punch label stored as the item Title.
     */
    protected function punchLabel(?int $status1): string
    {
        return match ($status1) {
            0 => 'TIME-IN',
            1 => 'TIME-OUT',
            4 => 'OT-IN',
            5 => 'OT-OUT',
            default => 'SCAN',
        };
    }
}
