<?php

namespace App\Services;

use App\Models\AttendanceLog;
use App\Models\User;
use Illuminate\Support\Carbon;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use RuntimeException;

/**
 * Imports raw biometric punch logs (one fingerprint/face scan per row) into
 * the application's attendance_logs table.
 *
 * The device export has no clock-in / clock-out marker, so the type is
 * inferred per employee per day: after collapsing accidental double-punches
 * within a short window, the earliest remaining scan is the clock-in and the
 * latest is the clock-out. Employees are matched to app users via
 * users.bio_metric_id.
 *
 * The expected columns (matched case-insensitively by header name) are those
 * produced by the biometric export: Department, Name, No., Date/Time,
 * Location ID, ID Number, VerifyCode, CardNo.
 */
class BiometricImportService
{
    /**
     * Scans by the same employee within this many minutes of a kept scan are
     * treated as accidental double-punches and dropped.
     */
    public const DEFAULT_DEDUPE_MINUTES = 3;

    public const DEVICE = 'biometric';

    /** Count of non-blank rows whose Date/Time could not be parsed in the last parse(). */
    public int $skippedDateRows = 0;

    /**
     * Parse an uploaded export file into raw punch rows.
     *
     * @return list<array{name: ?string, bio_metric_id: ?int, punched_at: Carbon, verify_code: ?string}>
     *
     * @throws RuntimeException When the file format cannot be read.
     */
    public function parse(string $path, ?string $extension = null): array
    {
        $extension = strtolower($extension ?? pathinfo($path, PATHINFO_EXTENSION));

        $matrix = $extension === 'csv'
            ? $this->readCsv($path)
            : $this->readSpreadsheet($path);

        return $this->rowsFromMatrix($matrix);
    }

    /**
     * Read a CSV/TXT export into a matrix of string cells.
     *
     * @return list<list<string>>
     */
    protected function readCsv(string $path): array
    {
        $handle = fopen($path, 'r');

        if ($handle === false) {
            throw new RuntimeException('Unable to open the uploaded file.');
        }

        $matrix = [];

        while (($row = fgetcsv($handle)) !== false) {
            $matrix[] = array_map(fn ($cell): string => trim((string) $cell), $row);
        }

        fclose($handle);

        return $matrix;
    }

    /**
     * Read an XLSX / OLE-based XLS export into a matrix of cell values.
     *
     * @return list<list<mixed>>
     *
     * @throws RuntimeException When the file is a legacy raw-BIFF stream that
     *                          PhpSpreadsheet cannot read.
     */
    protected function readSpreadsheet(string $path): array
    {
        try {
            $spreadsheet = IOFactory::load($path);
        } catch (\Throwable $e) {
            throw new RuntimeException(
                'This file looks like a legacy Excel export that cannot be read directly. '
                .'Please open it in Excel or LibreOffice and use "Save As" to export it as CSV '
                .'(or a modern .xlsx), then upload that file instead.',
                previous: $e,
            );
        }

        return $spreadsheet->getActiveSheet()->toArray(null, true, false, false);
    }

    /**
     * Convert a raw cell matrix into typed punch rows using the header row to
     * locate the relevant columns.
     *
     * @param  list<list<mixed>>  $matrix
     * @return list<array{name: ?string, bio_metric_id: ?int, punched_at: Carbon, verify_code: ?string}>
     */
    protected function rowsFromMatrix(array $matrix): array
    {
        if ($matrix === []) {
            throw new RuntimeException('The uploaded file is empty.');
        }

        $header = array_shift($matrix);
        $columns = $this->mapColumns($header);

        if (! isset($columns['datetime'])) {
            throw new RuntimeException('Could not find a "Date/Time" column in the file. Check that the header row is present.');
        }

        if (! isset($columns['id']) && ! isset($columns['no'])) {
            throw new RuntimeException('Could not find an "ID Number" (or "No.") column in the file.');
        }

        // Decide month-first vs day-first once for the whole file, since a
        // short date like "04-05-26" is otherwise ambiguous.
        $dayFirst = $this->detectDayFirst(array_column($matrix, $columns['datetime']));

        $punches = [];
        $this->skippedDateRows = 0;

        foreach ($matrix as $row) {
            $rawDate = $row[$columns['datetime']] ?? null;
            $punchedAt = $this->parseDateTime($rawDate, $dayFirst);

            if ($punchedAt === null) {
                // Count non-blank rows we couldn't parse so the reviewer is told.
                if (filled($rawDate)) {
                    $this->skippedDateRows++;
                }

                continue;
            }

            // Prefer "ID Number"; fall back to "No." when the device left it
            // blank (both columns hold the same employee id).
            $bioMetricId = $this->firstNumeric([
                $row[$columns['id']] ?? null,
                $row[$columns['no']] ?? null,
            ]);

            $punches[] = [
                'name' => isset($columns['name']) ? (trim((string) ($row[$columns['name']] ?? '')) ?: null) : null,
                'bio_metric_id' => $bioMetricId,
                'punched_at' => $punchedAt,
                'verify_code' => isset($columns['verify']) ? (trim((string) ($row[$columns['verify']] ?? '')) ?: null) : null,
            ];
        }

        return $punches;
    }

    /**
     * Locate the columns we care about by normalised header name. "ID Number"
     * and "No." are tracked separately so a blank "ID Number" cell can fall
     * back to "No." per row.
     *
     * @param  list<mixed>  $header
     * @return array{name?: int, id?: int, no?: int, datetime?: int, verify?: int}
     */
    protected function mapColumns(array $header): array
    {
        $columns = [];

        foreach ($header as $index => $label) {
            $key = preg_replace('/[^a-z0-9]/', '', strtolower((string) $label));

            match (true) {
                $key === 'name' => $columns['name'] ??= $index,
                $key === 'idnumber' => $columns['id'] ??= $index,
                $key === 'no' => $columns['no'] ??= $index,
                $key === 'datetime' => $columns['datetime'] ??= $index,
                $key === 'verifycode' => $columns['verify'] ??= $index,
                default => null,
            };
        }

        return $columns;
    }

    /**
     * Return the first numeric value from the candidates as an int, or null.
     *
     * @param  list<mixed>  $candidates
     */
    protected function firstNumeric(array $candidates): ?int
    {
        foreach ($candidates as $candidate) {
            if (is_numeric($candidate)) {
                return (int) $candidate;
            }
        }

        return null;
    }

    /**
     * Parse a cell into a Carbon instance, tolerating the various string
     * formats the device and Excel produce, plus numeric Excel serials.
     *
     * Parsing is done by explicit regex rather than Carbon::createFromFormat,
     * which matches too leniently (e.g. it would read "26" as the year 0026 for
     * a "Y" token). Exports vary by separator (/ or -), year width (2 or 4),
     * clock (12- or 24-hour), and optional seconds. $dayFirst chooses day/month
     * order for otherwise-ambiguous short dates.
     */
    protected function parseDateTime(mixed $value, bool $dayFirst = false): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return Carbon::instance(ExcelDate::excelToDateTimeObject((float) $value));
        }

        $value = trim((string) $value);

        $pattern = '#^(\d{1,4})[-/](\d{1,2})[-/](\d{1,4})[ T]+(\d{1,2}):(\d{2})(?::(\d{2}))?\s*([AaPp][Mm])?$#';

        if (! preg_match($pattern, $value, $m)) {
            return null;
        }

        [$year, $month, $day] = $this->resolveYmd($m[1], (int) $m[2], (int) $m[3], $dayFirst);

        $hour = (int) $m[4];
        $minute = (int) $m[5];
        $second = ($m[6] ?? '') !== '' ? (int) $m[6] : 0;

        $meridiem = strtoupper($m[7] ?? '');

        if ($meridiem === 'PM' && $hour < 12) {
            $hour += 12;
        } elseif ($meridiem === 'AM' && $hour === 12) {
            $hour = 0;
        }

        if ($month < 1 || $month > 12 || $day < 1 || $day > 31 || $hour > 23 || $minute > 59 || $second > 59) {
            return null;
        }

        try {
            return Carbon::create($year, $month, $day, $hour, $minute, $second);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Resolve the three date fields into [year, month, day].
     *
     * A 4-digit first field means ISO order (Y-m-d). Otherwise the third field
     * is the year (2-digit years pivot into the 2000s) and the first two are
     * month/day — order taken from any unambiguous value (> 12), else $dayFirst.
     *
     * @return array{0: int, 1: int, 2: int}
     */
    protected function resolveYmd(string $first, int $second, int $third, bool $dayFirst): array
    {
        if (strlen($first) === 4) {
            return [(int) $first, $second, $third];
        }

        $year = $third < 100 ? $third + 2000 : $third;
        $a = (int) $first;

        [$month, $day] = match (true) {
            $a > 12 => [$second, $a],     // first field can only be a day
            $second > 12 => [$a, $second], // second field can only be a day
            $dayFirst => [$second, $a],
            default => [$a, $second],
        };

        return [$year, $month, $day];
    }

    /**
     * Decide whether short dates in the file are day-first by looking for an
     * unambiguous row (a first field > 12 means day-first; a second field > 12
     * means month-first). Defaults to month-first — the device's native order
     * (e.g. "12/16/2025") — when every short date is ambiguous.
     *
     * @param  list<mixed>  $rawDates
     */
    protected function detectDayFirst(array $rawDates): bool
    {
        foreach ($rawDates as $raw) {
            if (! is_string($raw) || ! preg_match('/^\s*(\d{1,2})[-\/](\d{1,2})[-\/]/', $raw, $m)) {
                continue;
            }

            if ((int) $m[1] > 12) {
                return true;  // first field can only be a day
            }

            if ((int) $m[2] > 12) {
                return false; // second field can only be a day → month is first
            }
        }

        return false;
    }

    /**
     * Build reviewable per-employee, per-day rows from raw punches.
     *
     * Each row pairs the day's first and last scan into a clock-in / clock-out,
     * after collapsing double-punches, and is flagged so the reviewer can spot
     * unmatched IDs and single-punch days before committing.
     *
     * @param  list<array{name: ?string, bio_metric_id: ?int, punched_at: Carbon, verify_code: ?string}>  $punches
     * @return list<array{
     *     key: string, bio_metric_id: ?int, employee_name: ?string, user_id: ?int,
     *     date: string, time_in: ?string, time_out: ?string, punch_count: int, status: string,
     * }>
     */
    public function buildPreview(array $punches, int $dedupeMinutes = self::DEFAULT_DEDUPE_MINUTES): array
    {
        $bioMetricIds = collect($punches)->pluck('bio_metric_id')->filter()->unique()->values();

        $usersByBioId = User::query()
            ->whereIn('bio_metric_id', $bioMetricIds)
            ->get(['id', 'name', 'bio_metric_id'])
            ->keyBy('bio_metric_id');

        // Group punches by employee + calendar day.
        $groups = [];

        foreach ($punches as $punch) {
            $dayKey = ($punch['bio_metric_id'] ?? 'unknown').'|'.$punch['punched_at']->toDateString();
            $groups[$dayKey][] = $punch;
        }

        $rows = [];

        foreach ($groups as $group) {
            $sorted = collect($group)->sortBy(fn (array $p): int => $p['punched_at']->getTimestamp())->values();
            $kept = $this->collapseDoublePunches($sorted->all(), $dedupeMinutes);

            $first = $kept[0];
            $last = end($kept);
            $bioMetricId = $first['bio_metric_id'];
            $user = $bioMetricId !== null ? $usersByBioId->get($bioMetricId) : null;

            $timeIn = $first['punched_at'];
            $timeOut = count($kept) > 1 ? $last['punched_at'] : null;

            $status = match (true) {
                $user === null => 'unmatched',
                $timeOut === null => 'single_punch',
                default => 'ok',
            };

            $rows[] = [
                'key' => $bioMetricId.'-'.$timeIn->toDateString(),
                'bio_metric_id' => $bioMetricId,
                'employee_name' => $user?->name ?? $first['name'],
                'user_id' => $user?->id,
                'date' => $timeIn->toDateString(),
                'time_in' => $timeIn->format('Y-m-d H:i:s'),
                'time_out' => $timeOut?->format('Y-m-d H:i:s'),
                'punch_count' => count($sorted),
                'status' => $status,
            ];
        }

        usort($rows, fn (array $a, array $b): int => [$a['date'], $a['employee_name'] ?? ''] <=> [$b['date'], $b['employee_name'] ?? '']);

        return $rows;
    }

    /**
     * Drop scans that fall within $minutes of the previous kept scan.
     *
     * @param  list<array{name: ?string, bio_metric_id: ?int, punched_at: Carbon, verify_code: ?string}>  $sorted
     * @return non-empty-list<array{name: ?string, bio_metric_id: ?int, punched_at: Carbon, verify_code: ?string}>
     */
    protected function collapseDoublePunches(array $sorted, int $minutes): array
    {
        $kept = [array_shift($sorted)];

        foreach ($sorted as $punch) {
            $previous = end($kept);

            if (abs($previous['punched_at']->diffInMinutes($punch['punched_at'])) >= $minutes) {
                $kept[] = $punch;
            }
        }

        return $kept;
    }

    /**
     * Insert the reviewed rows into attendance_logs.
     *
     * Only rows with a resolved user are written. A clock-in is always created;
     * a clock-out is created when the row has a time-out. Existing logs for the
     * same user, type and timestamp are skipped so re-importing is safe.
     *
     * @param  list<array{user_id: ?int, time_in: ?string, time_out: ?string}>  $rows
     * @return array{clock_ins: int, clock_outs: int, skipped_existing: int, skipped_unmatched: int}
     */
    public function commit(array $rows): array
    {
        $summary = ['clock_ins' => 0, 'clock_outs' => 0, 'skipped_existing' => 0, 'skipped_unmatched' => 0];

        foreach ($rows as $row) {
            if (empty($row['user_id'])) {
                $summary['skipped_unmatched']++;

                continue;
            }

            if (! empty($row['time_in']) && $this->createLog((int) $row['user_id'], 'clockin', $row['time_in'])) {
                $summary['clock_ins']++;
            } elseif (! empty($row['time_in'])) {
                $summary['skipped_existing']++;
            }

            if (! empty($row['time_out']) && $this->createLog((int) $row['user_id'], 'clockout', $row['time_out'])) {
                $summary['clock_outs']++;
            } elseif (! empty($row['time_out'])) {
                $summary['skipped_existing']++;
            }
        }

        return $summary;
    }

    /**
     * Create a single attendance log, skipping (returning false) when an
     * identical user/type/timestamp record already exists.
     */
    protected function createLog(int $userId, string $type, string $timestamp): bool
    {
        $loggedAt = Carbon::parse($timestamp);

        $exists = AttendanceLog::query()
            ->where('user_id', $userId)
            ->where('type', $type)
            ->where('created_at', $loggedAt)
            ->exists();

        if ($exists) {
            return false;
        }

        AttendanceLog::create([
            'user_id' => $userId,
            'type' => $type,
            'device' => self::DEVICE,
            'remarks' => 'Imported from biometrics',
            'created_at' => $loggedAt,
            'updated_at' => $loggedAt,
        ]);

        return true;
    }
}
