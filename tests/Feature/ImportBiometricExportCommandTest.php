<?php

use App\Models\AttendanceLog;
use App\Models\User;

/**
 * A scanner export with the device's own column headers.
 *
 * @param  list<array{0: string, 1: string, 2: string}>  $punches  [name, bio id, date/time]
 */
function exportFile(array $punches): string
{
    $grid = [['Department', 'Name', 'No.', 'Date/Time', 'Location ID', 'ID Number', 'VerifyCode', 'CardNo']];

    foreach ($punches as [$name, $bioId, $dateTime]) {
        $grid[] = ['DIGITAL FEET', $name, $bioId, $dateTime, '1', $bioId, 'FP', ''];
    }

    return biffFile($grid);
}

it('imports a raw scanner export without any conversion step', function () {
    $user = User::factory()->create(['bio_metric_id' => 110]);

    $path = exportFile([
        ['Danilo', '110', '23/09/2026 10:03:28 am'],
        ['Danilo', '110', '23/09/2026 6:01:12 pm'],
    ]);

    $this->artisan('attendance:import', ['file' => $path])
        ->expectsConfirmation('Import 1 day(s) of attendance?', 'yes')
        ->assertSuccessful();

    $logs = AttendanceLog::query()->where('user_id', $user->id)->orderBy('created_at')->get();

    expect($logs)->toHaveCount(2)
        ->and($logs[0]->type)->toBe('clockin')
        ->and($logs[0]->created_at->toDateTimeString())->toBe('2026-09-23 10:03:28')
        ->and($logs[0]->device)->toBe('biometric')
        ->and($logs[1]->type)->toBe('clockout')
        ->and($logs[1]->created_at->toDateTimeString())->toBe('2026-09-23 18:01:12');

    @unlink($path);
});

it('writes nothing on a dry run', function () {
    User::factory()->create(['bio_metric_id' => 110]);

    $path = exportFile([['Danilo', '110', '23/09/2026 10:03:28 am']]);

    $this->artisan('attendance:import', ['file' => $path, '--dry-run' => true])
        ->expectsOutputToContain('Dry run')
        ->assertSuccessful();

    expect(AttendanceLog::query()->count())->toBe(0);

    @unlink($path);
});

it('leaves a single scan as an open shift', function () {
    $user = User::factory()->create(['bio_metric_id' => 110]);

    $path = exportFile([['Danilo', '110', '23/09/2026 10:03:28 am']]);

    $this->artisan('attendance:import', ['file' => $path])
        ->expectsConfirmation('Import 1 day(s) of attendance?', 'yes')
        ->assertSuccessful();

    $logs = AttendanceLog::query()->where('user_id', $user->id)->get();

    expect($logs)->toHaveCount(1)
        ->and($logs[0]->type)->toBe('clockin');

    @unlink($path);
});

it('skips employees whose biometric id is not on file', function () {
    User::factory()->create(['bio_metric_id' => 110]);

    $path = exportFile([
        ['Danilo', '110', '23/09/2026 10:03:28 am'],
        ['Ghost', '999', '23/09/2026 10:05:00 am'],
    ]);

    $this->artisan('attendance:import', ['file' => $path])
        ->expectsOutputToContain('1 row(s) have no matching employee')
        ->expectsConfirmation('Import 1 day(s) of attendance?', 'yes')
        ->assertSuccessful();

    expect(AttendanceLog::query()->count())->toBe(1);

    @unlink($path);
});

it('is safe to re-run against an export that already partly synced', function () {
    User::factory()->create(['bio_metric_id' => 110]);

    $path = exportFile([['Danilo', '110', '23/09/2026 10:03:28 am']]);

    foreach (range(1, 2) as $ignored) {
        $this->artisan('attendance:import', ['file' => $path])
            ->expectsConfirmation('Import 1 day(s) of attendance?', 'yes')
            ->assertSuccessful();
    }

    expect(AttendanceLog::query()->count())->toBe(1);

    @unlink($path);
});

it('does not write when the confirmation is declined', function () {
    User::factory()->create(['bio_metric_id' => 110]);

    $path = exportFile([['Danilo', '110', '23/09/2026 10:03:28 am']]);

    $this->artisan('attendance:import', ['file' => $path])
        ->expectsConfirmation('Import 1 day(s) of attendance?', 'no')
        ->expectsOutputToContain('Aborted')
        ->assertSuccessful();

    expect(AttendanceLog::query()->count())->toBe(0);

    @unlink($path);
});

it('collapses repeated scans within the dedupe window', function () {
    $user = User::factory()->create(['bio_metric_id' => 110]);

    // Three scans seconds apart — an impatient double-tap, one real punch.
    $path = exportFile([
        ['Danilo', '110', '23/09/2026 10:03:28 am'],
        ['Danilo', '110', '23/09/2026 10:03:41 am'],
        ['Danilo', '110', '23/09/2026 10:04:02 am'],
    ]);

    $this->artisan('attendance:import', ['file' => $path])
        ->expectsConfirmation('Import 1 day(s) of attendance?', 'yes')
        ->assertSuccessful();

    expect(AttendanceLog::query()->where('user_id', $user->id)->count())->toBe(1);

    @unlink($path);
});

it('honours a custom dedupe window', function () {
    $user = User::factory()->create(['bio_metric_id' => 110]);

    $path = exportFile([
        ['Danilo', '110', '23/09/2026 10:03:28 am'],
        ['Danilo', '110', '23/09/2026 10:04:02 am'],
    ]);

    // With no dedupe the second scan stands, closing the shift.
    $this->artisan('attendance:import', ['file' => $path, '--dedupe' => 0])
        ->expectsConfirmation('Import 1 day(s) of attendance?', 'yes')
        ->assertSuccessful();

    expect(AttendanceLog::query()->where('user_id', $user->id)->pluck('type')->all())
        ->toBe(['clockin', 'clockout']);

    @unlink($path);
});

it('fails on an unreadable path', function () {
    $this->artisan('attendance:import', ['file' => '/no/such/export.xls'])
        ->expectsOutputToContain('File not readable')
        ->assertFailed();
});

it('reports a file it cannot make sense of', function () {
    $path = tempnam(sys_get_temp_dir(), 'junk').'.xls';
    file_put_contents($path, 'this is not a spreadsheet at all');

    $this->artisan('attendance:import', ['file' => $path])->assertFailed();

    @unlink($path);
});
