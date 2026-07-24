<?php

namespace App\Console\Commands;

use App\Enum\AttendanceStatus;
use App\Models\OverTimeRequest;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Throwable;

/**
 * One-off importer for the legacy SharePoint "Overtime" list exported as CSV.
 *
 * Rows are matched to users by the "Title" column (the employee's email) and
 * upserted on the SharePoint list item id, so the command is safe to re-run.
 * Rows whose employee email has no matching user are skipped and logged.
 */
class ImportSharepointOvertime extends Command
{
    protected $signature = 'overtime:import-sharepoint
                            {path : Absolute path to the exported Overtime.csv}
                            {--dry-run : Parse and report without writing to the database}';

    protected $description = 'Import the legacy SharePoint Overtime list (CSV) into over_time_requests';

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
            $email = strtolower(trim((string) ($row['Title'] ?? '')));

            $userId = $resolve($email);

            if ($userId === null) {
                $this->warn("Row {$rowNumber} (SP #{$sharepointId}): no user for \"{$email}\", skipped.");
                $skipped++;

                continue;
            }

            try {
                $attributes = [
                    'user_id' => $userId,
                    'manager_id' => $resolve($row['Manager'] ?? null),
                    'approved_by' => $resolve($row['ApprovedBy'] ?? null),
                    'request_date' => $this->parseDate($row['Date'] ?? null),
                    'hours' => (float) ($row['Hours'] ?? 0),
                    'reason' => $this->decode($row['Reason'] ?? ''),
                    'status' => $this->mapStatus($row['OverallStatus'] ?? null) ?? AttendanceStatus::FOR_APPROVAL,
                    'manager_status' => $this->mapStatus($row['Status'] ?? null)?->value,
                    'remarks' => $this->decode($row['Remarks'] ?? ''),
                    'attachments_count' => (int) ($row['Attachments'] ?? 0),
                    'sharepoint_created_at' => $this->parseDate($row['Created'] ?? null),
                    'sharepoint_modified_at' => $this->parseDate($row['Modified'] ?? null),
                ];
            } catch (Throwable $e) {
                $this->warn("Row {$rowNumber} (SP #{$sharepointId}): {$e->getMessage()}, skipped.");
                $skipped++;

                continue;
            }

            if (! $dryRun) {
                OverTimeRequest::withoutEvents(function () use ($sharepointId, $attributes) {
                    OverTimeRequest::updateOrCreate(
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
     * Parse a SharePoint date/datetime (US format, e.g. "8/02/2023" or
     * "8/30/2023 3:05 AM"). Returns null for blanks.
     */
    private function parseDate(?string $value): ?CarbonImmutable
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        // Try the datetime form first ("8/30/2023 3:05 AM"), then the date-only
        // form ("8/02/2023"). createFromFormat throws on mismatch, so guard each.
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
