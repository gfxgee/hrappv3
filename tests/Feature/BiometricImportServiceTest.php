<?php

use App\Models\AttendanceLog;
use App\Models\User;
use App\Services\BiometricImportService;

/**
 * Write a CSV export fixture mirroring the biometric device's columns and
 * return its path. Each punch is [name, idNumber, 'M/D/YYYY h:mm:ss AM/PM'].
 *
 * @param  list<array{0: string, 1: int|string, 2: string}>  $punches
 */
function writeBiometricCsv(array $punches): string
{
    $path = tempnam(sys_get_temp_dir(), 'bio').'.csv';
    $handle = fopen($path, 'w');

    fputcsv($handle, ['Department', 'Name', 'No.', 'Date/Time', 'Location ID', 'ID Number', 'VerifyCode', 'CardNo']);

    foreach ($punches as [$name, $id, $dateTime]) {
        fputcsv($handle, ['ACME CORP', $name, $id, $dateTime, 1, $id, 'FP', $id]);
    }

    fclose($handle);

    return $path;
}

it('parses raw punches from a csv export', function () {
    $path = writeBiometricCsv([
        ['Vevien', 104, '12/17/2025 10:30:26 AM'],
        ['Vevien', 104, '12/17/2025 7:22:09 PM'],
    ]);

    $punches = app(BiometricImportService::class)->parse($path);

    expect($punches)->toHaveCount(2)
        ->and($punches[0]['bio_metric_id'])->toBe(104)
        ->and($punches[0]['name'])->toBe('Vevien')
        ->and($punches[0]['punched_at']->format('Y-m-d H:i:s'))->toBe('2025-12-17 10:30:26');
});

it('falls back to the No. column when ID Number is blank', function () {
    $path = tempnam(sys_get_temp_dir(), 'bio').'.csv';
    $handle = fopen($path, 'w');
    fputcsv($handle, ['Department', 'Name', 'No.', 'Date/Time', 'Location ID', 'ID Number', 'VerifyCode', 'CardNo']);
    // ID Number left blank; No. carries the same employee id.
    fputcsv($handle, ['ACME CORP', 'Vevien', 104, '12/17/2025 10:30:00 AM', 1, '', 'FP', 104]);
    fclose($handle);

    $punches = app(BiometricImportService::class)->parse($path);

    expect($punches[0]['bio_metric_id'])->toBe(104);
});

it('pairs the first and last scan of a day into clock-in and clock-out', function () {
    $user = User::factory()->create(['bio_metric_id' => 104]);

    $path = writeBiometricCsv([
        ['Vevien', 104, '12/17/2025 10:30:00 AM'],
        ['Vevien', 104, '12/17/2025 1:00:00 PM'],
        ['Vevien', 104, '12/17/2025 7:22:00 PM'],
    ]);

    $service = app(BiometricImportService::class);
    $rows = $service->buildPreview($service->parse($path));

    expect($rows)->toHaveCount(1);
    expect($rows[0])
        ->user_id->toBe($user->id)
        ->status->toBe('ok')
        ->time_in->toBe('2025-12-17 10:30:00')
        ->time_out->toBe('2025-12-17 19:22:00')
        ->punch_count->toBe(3);
});

it('collapses accidental double-punches within the dedupe window', function () {
    User::factory()->create(['bio_metric_id' => 104]);

    // Two scans 1 minute apart at clock-in, two scans 1 minute apart at clock-out.
    $path = writeBiometricCsv([
        ['Vevien', 104, '12/17/2025 8:00:00 AM'],
        ['Vevien', 104, '12/17/2025 8:01:00 AM'],
        ['Vevien', 104, '12/17/2025 5:00:00 PM'],
        ['Vevien', 104, '12/17/2025 5:01:00 PM'],
    ]);

    $service = app(BiometricImportService::class);
    $rows = $service->buildPreview($service->parse($path), dedupeMinutes: 3);

    expect($rows[0]['time_in'])->toBe('2025-12-17 08:00:00')
        ->and($rows[0]['time_out'])->toBe('2025-12-17 17:00:00')
        ->and($rows[0]['punch_count'])->toBe(4);
});

it('flags a single-punch day with no clock-out', function () {
    User::factory()->create(['bio_metric_id' => 104]);

    $path = writeBiometricCsv([['Vevien', 104, '12/16/2025 6:31:00 PM']]);

    $service = app(BiometricImportService::class);
    $rows = $service->buildPreview($service->parse($path));

    expect($rows[0]['status'])->toBe('single_punch')
        ->and($rows[0]['time_out'])->toBeNull();
});

it('flags punches whose biometric id matches no user', function () {
    $path = writeBiometricCsv([
        ['Ghost', 999, '12/16/2025 8:00:00 AM'],
        ['Ghost', 999, '12/16/2025 5:00:00 PM'],
    ]);

    $service = app(BiometricImportService::class);
    $rows = $service->buildPreview($service->parse($path));

    expect($rows[0]['status'])->toBe('unmatched')
        ->and($rows[0]['user_id'])->toBeNull();
});

it('commits matched rows as clock-in and clock-out logs', function () {
    $user = User::factory()->create(['bio_metric_id' => 104]);

    $summary = app(BiometricImportService::class)->commit([
        ['user_id' => $user->id, 'time_in' => '2025-12-17 08:00:00', 'time_out' => '2025-12-17 17:00:00'],
    ]);

    expect($summary['clock_ins'])->toBe(1)
        ->and($summary['clock_outs'])->toBe(1);

    $logs = AttendanceLog::where('user_id', $user->id)->orderBy('type')->get();
    expect($logs)->toHaveCount(2)
        ->and($logs->firstWhere('type', 'clockin')->device)->toBe('biometric')
        ->and($logs->firstWhere('type', 'clockin')->created_at->format('Y-m-d H:i:s'))->toBe('2025-12-17 08:00:00')
        ->and($logs->firstWhere('type', 'clockout')->created_at->format('Y-m-d H:i:s'))->toBe('2025-12-17 17:00:00');
});

it('skips unmatched rows and re-imports idempotently', function () {
    $user = User::factory()->create(['bio_metric_id' => 104]);

    $rows = [
        ['user_id' => $user->id, 'time_in' => '2025-12-17 08:00:00', 'time_out' => '2025-12-17 17:00:00'],
        ['user_id' => null, 'time_in' => '2025-12-17 08:00:00', 'time_out' => '2025-12-17 17:00:00'],
    ];

    $service = app(BiometricImportService::class);

    $first = $service->commit($rows);
    expect($first['clock_ins'])->toBe(1)
        ->and($first['skipped_unmatched'])->toBe(1);

    // Re-running must not create duplicates.
    $second = $service->commit($rows);
    expect($second['clock_ins'])->toBe(0)
        ->and($second['skipped_existing'])->toBe(2);

    expect(AttendanceLog::where('user_id', $user->id)->count())->toBe(2);
});
