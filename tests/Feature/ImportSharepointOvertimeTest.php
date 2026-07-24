<?php

use App\Enum\AttendanceStatus;
use App\Models\OverTimeRequest;
use App\Models\User;

function writeOvertimeCsv(array $rows): string
{
    $header = 'ID,Title,Date,Hours,Reason,Approver,Manager,Status,Remarks,ApprovedBy,OverallStatus,Modified,Created,Attachments';
    // Prepend a UTF-8 BOM to mirror the real SharePoint export.
    $lines = ["\xEF\xBB\xBF".$header];

    foreach ($rows as $row) {
        $lines[] = collect($row)
            ->map(fn ($value) => '"'.str_replace('"', '""', (string) $value).'"')
            ->implode(',');
    }

    $path = tempnam(sys_get_temp_dir(), 'ot').'.csv';
    file_put_contents($path, implode("\r\n", $lines));

    return $path;
}

it('imports matched rows, resolving emails to user ids and decoding entities', function () {
    $employee = User::factory()->create(['email' => 'nik@digitalfeet.com']);
    $manager = User::factory()->create(['email' => 'jonathan@digitalfeet.com']);
    $approver = User::factory()->create(['email' => 'nico@digitalfeet.com']);

    $path = writeOvertimeCsv([[
        7, 'nik@digitalfeet.com', '7/29/2023', '3',
        'Fixed links&#58; see teams &amp; docs', 'Approved', 'jonathan@digitalfeet.com',
        'Approved', '[nico@digitalfeet.com]', 'nico@digitalfeet.com', 'Approved',
        '8/30/2023 3:05 AM', '7/28/2023 12:23 PM', '0',
    ]]);

    $this->artisan('overtime:import-sharepoint', ['path' => $path])->assertSuccessful();

    $record = OverTimeRequest::where('sharepoint_id', 7)->sole();

    expect($record->user_id)->toBe($employee->id)
        ->and($record->manager_id)->toBe($manager->id)
        ->and($record->approved_by)->toBe($approver->id)
        ->and($record->hours)->toBe(3.0)
        ->and($record->reason)->toBe('Fixed links: see teams & docs')
        ->and($record->status)->toBe(AttendanceStatus::APPROVED)
        ->and($record->request_date->toDateString())->toBe('2023-07-29')
        // Not cast on the model, so it comes back as a raw datetime string.
        ->and($record->sharepoint_created_at)->toBe('2023-07-28 12:23:00')
        // "Filed" (created_at) must reflect the SharePoint Created date, not today.
        ->and($record->created_at->format('Y-m-d H:i'))->toBe('2023-07-28 12:23')
        ->and($record->updated_at->format('Y-m-d H:i'))->toBe('2023-08-30 03:05');

    @unlink($path);
});

it('skips rows whose employee email has no matching user', function () {
    User::factory()->create(['email' => 'nik@digitalfeet.com']);

    $path = writeOvertimeCsv([
        [7, 'nik@digitalfeet.com', '7/29/2023', '3', 'ok', 'Approved', '', 'Approved', '', '', 'Approved', '', '', '0'],
        [8, 'ghost@digitalfeet.com', '7/30/2023', '2', 'nope', 'Approved', '', 'Approved', '', '', 'Approved', '', '', '0'],
    ]);

    $this->artisan('overtime:import-sharepoint', ['path' => $path])
        ->expectsOutputToContain('no user for "ghost@digitalfeet.com"')
        ->assertSuccessful();

    expect(OverTimeRequest::count())->toBe(1)
        ->and(OverTimeRequest::where('sharepoint_id', 8)->exists())->toBeFalse();

    @unlink($path);
});

it('is idempotent when re-run on the same list', function () {
    User::factory()->create(['email' => 'nik@digitalfeet.com']);

    $path = writeOvertimeCsv([
        [7, 'nik@digitalfeet.com', '7/29/2023', '3', 'first', 'Approved', '', 'Approved', '', '', 'Approved', '', '', '0'],
    ]);

    $this->artisan('overtime:import-sharepoint', ['path' => $path])->assertSuccessful();
    $this->artisan('overtime:import-sharepoint', ['path' => $path])->assertSuccessful();

    expect(OverTimeRequest::where('sharepoint_id', 7)->count())->toBe(1);

    @unlink($path);
});

it('does not write anything on a dry run', function () {
    User::factory()->create(['email' => 'nik@digitalfeet.com']);

    $path = writeOvertimeCsv([
        [7, 'nik@digitalfeet.com', '7/29/2023', '3', 'x', 'Approved', '', 'Approved', '', '', 'Approved', '', '', '0'],
    ]);

    $this->artisan('overtime:import-sharepoint', ['path' => $path, '--dry-run' => true])
        ->expectsOutputToContain('Would import 1')
        ->assertSuccessful();

    expect(OverTimeRequest::count())->toBe(0);

    @unlink($path);
});
