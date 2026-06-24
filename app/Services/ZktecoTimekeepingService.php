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
 * Unlike the DF Portal's version, employees are matched to an email locally
 * (users.bio_metric_id), so this never reads the Active Employees workbook — it
 * only discovers the list id once and creates list items.
 */
class ZktecoTimekeepingService
{
    /**
     * Acquire (and cache) an app-only Graph access token.
     */
    protected function accessToken(): ?string
    {
        $cached = Cache::get('zkteco_sharepoint_token');

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
            Cache::put('zkteco_sharepoint_token', $token, max(60, $expiresIn - 60));

            return $token;
        }

        return null;
    }

    /**
     * Discover and cache the target list id by display name.
     */
    protected function timekeepingListId(string $token): ?string
    {
        return Cache::remember('zkteco_timekeeping_list_id', now()->addDay(), function () use ($token): ?string {
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
