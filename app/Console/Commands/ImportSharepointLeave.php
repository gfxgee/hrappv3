<?php

namespace App\Console\Commands;

use App\Enum\AttendanceStatus;
use App\Enum\LeaveType;
use App\Models\LeaveRequest;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Throwable;

/**
 * One-off importer for the legacy SharePoint "Timeoff" list exported as CSV.
 *
 * Rows are matched to users by the "Email" column and upserted on the SharePoint
 * list item id, so the command is safe to re-run. Rows whose employee email has
 * no matching user are skipped and logged. Each record is a single-day leave, so
 * the list's single "Date" column fills both start_date and end_date.
 */
class ImportSharepointLeave extends Command
{
    protected $signature = 'leave:import-sharepoint
                            {path : Absolute path to the exported Timeoff.csv}
                            {--dry-run : Parse and report without writing to the database}';

    protected $description = 'Import the legacy SharePoint Timeoff list (CSV) into leave_requests';

    /**
     * SharePoint "Title" leave label => LeaveType. Matched case-insensitively.
     * "DF Sportsfest" is not a real leave type, so it is imported as vacation.
     *
     * @var array<string, LeaveType>
     */
    private array $typeOverrides = [
        'df sportsfest' => LeaveType::VACATION,
    ];

    public function handle(): int
    {
        $path = $this->argument('path');

        if (! is_readable($path)) {
            $this->error("File not readable: {$path}");

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');

        $handle = fopen($path, 'r');

        if ($handle === false) {
            $this->error("Unable to open file: {$path}");

            return self::FAILURE;
        }

        // Cache email => user id lookups so a large file makes one query per email.
        $userIdCache = [];
        $resolve = function (?string $email) use (&$userIdCache): ?int {
            $email = strtolower(trim((string) $email));

            if ($email === '') {
                return null;
            }

            if (! array_key_exists($email, $userIdCache)) {
                $userIdCache[$email] = User::where('email', $email)->value('id');
            }

            return $userIdCache[$email];
        };

        // Build a plain-label => LeaveType map from the enum, plus the overrides.
        $typeMap = $this->typeOverrides;

        foreach (LeaveType::all() as $type) {
            $typeMap[strtolower($type->plainLabel())] = $type;
        }

        $header = fgetcsv($handle);

        if ($header === false) {
            fclose($handle);
            $this->error('CSV appears to be empty.');

            return self::FAILURE;
        }

        // A UTF-8 BOM sits before the opening quote of the first field, so
        // fgetcsv treats that field as unquoted and keeps its literal quotes.
        // Strip both the BOM and the surrounding quotes so the key is "ID".
        $header[0] = trim(preg_replace("/^\xEF\xBB\xBF/", '', (string) $header[0]), '"');

        $imported = 0;
        $skipped = 0;
        $rowNumber = 1; // header consumed

        while (($values = fgetcsv($handle)) !== false) {
            $rowNumber++;

            // Skip fully blank lines that some exports append.
            if ($values === [null] || $values === ['']) {
                continue;
            }

            $row = @array_combine($header, array_pad($values, count($header), null));

            if ($row === false) {
                $this->warn("Row {$rowNumber}: column count mismatch, skipped.");
                $skipped++;

                continue;
            }

            $sharepointId = trim((string) ($row['ID'] ?? ''));
            $email = strtolower(trim((string) ($row['Email'] ?? '')));

            $userId = $resolve($email);

            if ($userId === null) {
                $this->warn("Row {$rowNumber} (SP #{$sharepointId}): no user for \"{$email}\", skipped.");
                $skipped++;

                continue;
            }

            $requestType = $typeMap[strtolower(trim((string) ($row['Title'] ?? '')))] ?? null;

            if ($requestType === null) {
                $this->warn("Row {$rowNumber} (SP #{$sharepointId}): unknown leave type \"{$row['Title']}\", skipped.");
                $skipped++;

                continue;
            }

            try {
                $date = $this->parseDate($row['Date'] ?? null);
                $sharepointCreated = $this->parseDate($row['Created'] ?? null);

                $attributes = [
                    'user_id' => $userId,
                    'manager_id' => $resolve($row['Manager'] ?? null),
                    'approved_by' => $resolve($row['ApprovedBy'] ?? null),
                    'request_type' => $requestType,
                    // Single-day leave: the one Date fills both ends of the range.
                    'start_date' => $date,
                    'end_date' => $date,
                    'start_time' => $this->cleanTime($row['TimeStart'] ?? null),
                    'end_time' => $this->cleanTime($row['TimeEnd'] ?? null),
                    'reason' => $this->decode($row['Reason'] ?? ''),
                    'status' => $this->mapStatus($row['OverallStatus'] ?? null) ?? AttendanceStatus::FOR_APPROVAL,
                    'manager_status' => $this->mapStatus($row['Status'] ?? null)?->value,
                    'hr_approved' => $this->parseBool($row['HR'] ?? null),
                    'remarks' => $this->decode($row['Remarks'] ?? ''),
                    'sharepoint_created_at' => $sharepointCreated,
                ];

                // The "Filed" date shown in the app is created_at, so stamp the
                // Eloquent timestamp from SharePoint rather than the import time.
                if ($sharepointCreated !== null) {
                    $attributes['created_at'] = $sharepointCreated;
                    $attributes['updated_at'] = $sharepointCreated;
                }
            } catch (Throwable $e) {
                $this->warn("Row {$rowNumber} (SP #{$sharepointId}): {$e->getMessage()}, skipped.");
                $skipped++;

                continue;
            }

            if (! $dryRun) {
                LeaveRequest::withoutEvents(function () use ($sharepointId, $attributes) {
                    LeaveRequest::updateOrCreate(
                        ['sharepoint_id' => (int) $sharepointId],
                        $attributes,
                    );
                });
            }

            $imported++;
        }

        fclose($handle);

        $verb = $dryRun ? 'Would import' : 'Imported';
        $this->info("{$verb} {$imported} row(s); skipped {$skipped}.");

        return self::SUCCESS;
    }

    /**
     * Decode HTML entities SharePoint stores in text fields (e.g. &#58; &amp;)
     * and normalise line endings.
     */
    private function decode(?string $value): string
    {
        $value = html_entity_decode((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(str_replace(["\r\n", "\r"], "\n", $value));
    }

    /**
     * Normalise a SharePoint time cell ("10:00") to a plain "H:i" string, or
     * null when blank.
     */
    private function cleanTime(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /**
     * Parse a SharePoint date/datetime (US format, e.g. "7/24/2026" or
     * "7/23/2026 7:02 PM"). Returns null for blanks.
     */
    private function parseDate(?string $value): ?CarbonImmutable
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        // Try the datetime form first ("7/23/2026 7:02 PM"), then date-only.
        // createFromFormat throws on mismatch, so guard each.
        foreach (['n/j/Y g:i A', 'n/j/Y'] as $format) {
            try {
                return CarbonImmutable::createFromFormat($format, $value)->startOfMinute();
            } catch (Throwable) {
                continue;
            }
        }

        return CarbonImmutable::parse($value);
    }

    /**
     * Interpret the SharePoint "HR" flag. Only an explicit "true" counts as
     * approved; "false", blanks, and stray values map to false.
     */
    private function parseBool(?string $value): bool
    {
        return strtolower(trim((string) $value)) === 'true';
    }

    /**
     * Map a SharePoint status label ("Approved", "For Approval", ...) to the
     * app's AttendanceStatus. Returns null for blank/unknown values.
     */
    private function mapStatus(?string $value): ?AttendanceStatus
    {
        $normalized = str_replace(' ', '', strtolower(trim((string) $value)));

        if ($normalized === '') {
            return null;
        }

        return AttendanceStatus::tryFrom($normalized);
    }
}
