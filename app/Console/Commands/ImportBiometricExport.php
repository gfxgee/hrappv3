<?php

namespace App\Console\Commands;

use App\Services\BiometricImportService;
use Illuminate\Console\Command;
use Throwable;

/**
 * Imports a biometric device export straight from the command line — the
 * fallback for days the scanner's live sync doesn't deliver.
 *
 * Reads the scanner's own raw-BIFF ".xls" as well as .xlsx and .csv, then
 * applies the same rules as the Import Attendance page: punches are grouped
 * per employee per day, double-scans within the dedupe window are collapsed,
 * and the day's first and last remaining scans become the clock-in and
 * clock-out. Employees are matched on users.bio_metric_id.
 *
 * Re-running is safe — a punch that already exists is skipped, so it can be
 * pointed at an export that partly synced.
 */
class ImportBiometricExport extends Command
{
    protected $signature = 'attendance:import
                            {file : Path to the biometric export (.xls, .xlsx or .csv)}
                            {--dry-run : Show what would be imported without writing}
                            {--dedupe= : Minutes within which repeated scans are collapsed}';

    protected $description = 'Import a biometric device export into attendance logs';

    public function handle(BiometricImportService $service): int
    {
        $file = (string) $this->argument('file');

        if (! is_readable($file)) {
            $this->components->error("File not readable: {$file}");

            return self::FAILURE;
        }

        $dedupe = $this->option('dedupe') !== null
            ? max(0, (int) $this->option('dedupe'))
            : BiometricImportService::DEFAULT_DEDUPE_MINUTES;

        try {
            $punches = $service->parse($file);
        } catch (Throwable $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        if ($punches === []) {
            $this->components->warn('No punches found in this file.');

            return self::SUCCESS;
        }

        $this->components->info(sprintf(
            '%d punch(es) read%s.',
            count($punches),
            $service->skippedDateRows > 0
                ? sprintf(', %d row(s) skipped with an unreadable date', $service->skippedDateRows)
                : '',
        ));

        $rows = $service->buildPreview($punches, $dedupe);

        $this->table(
            ['Employee', 'Bio ID', 'Date', 'Clock in', 'Clock out', 'Scans', 'Status'],
            array_map(fn (array $row): array => [
                $row['employee_name'] ?? '—',
                $row['bio_metric_id'] ?? '—',
                $row['date'],
                $this->timeOnly($row['time_in']),
                $this->timeOnly($row['time_out']),
                $row['punch_count'],
                $this->statusLabel($row['status']),
            ], $rows),
        );

        $unmatched = count(array_filter($rows, fn (array $row): bool => $row['status'] === 'unmatched'));

        if ($unmatched > 0) {
            $this->components->warn(
                "{$unmatched} row(s) have no matching employee and will be skipped. "
                .'Set the employee\'s Biometric ID to import them.'
            );
        }

        if ($this->option('dry-run')) {
            $this->components->info('Dry run — nothing was written.');

            return self::SUCCESS;
        }

        $writable = count($rows) - $unmatched;

        if ($writable < 1) {
            $this->components->error('Nothing to import — no rows matched an employee.');

            return self::FAILURE;
        }

        if (! $this->confirm("Import {$writable} day(s) of attendance?", true)) {
            $this->components->info('Aborted — nothing was written.');

            return self::SUCCESS;
        }

        $summary = $service->commit($rows);

        $this->components->info(sprintf(
            '%d clock-in(s) and %d clock-out(s) imported. %d already existed, %d unmatched.',
            $summary['clock_ins'],
            $summary['clock_outs'],
            $summary['skipped_existing'],
            $summary['skipped_unmatched'],
        ));

        return self::SUCCESS;
    }

    /**
     * Times are shown without the date, which the row already carries.
     */
    protected function timeOnly(?string $timestamp): string
    {
        return $timestamp === null ? '—' : substr($timestamp, 11, 5);
    }

    protected function statusLabel(string $status): string
    {
        return match ($status) {
            'unmatched' => 'no employee',
            'single_punch' => 'no clock-out',
            default => 'ok',
        };
    }
}
