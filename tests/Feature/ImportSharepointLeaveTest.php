<?php

use App\Enum\AttendanceStatus;
use App\Enum\LeaveType;
use App\Models\LeaveRequest;
use App\Models\User;

function writeTimeoffCsv(array $rows): string
{
    $header = 'ID,Created,Title,Date,Email,TimeStart,TimeEnd,Reason,Approver,Manager,Status,HR,Remarks,ApprovedBy,OverallStatus,LeavesID,Created By,Approval status';
    // Prepend a UTF-8 BOM to mirror the real SharePoint export.
    $lines = ["\xEF\xBB\xBF".$header];

    foreach ($rows as $row) {
        $lines[] = collect($row)
            ->map(fn ($value) => '"'.str_replace('"', '""', (string) $value).'"')
            ->implode(',');
    }

    $path = tempnam(sys_get_temp_dir(), 'leave').'.csv';
    file_put_contents($path, implode("\r\n", $lines));

    return $path;
}

/**
 * @param  array<string, string>  $overrides
 */
function timeoffRow(array $overrides = []): array
{
    return array_values(array_merge([
        'ID' => '3773',
        'Created' => '7/20/2026 7:07 PM',
        'Title' => 'Sick Leave',
        'Date' => '7/21/2026',
        'Email' => 'vevien@digitalfeet.com',
        'TimeStart' => '10:00',
        'TimeEnd' => '12:00',
        'Reason' => 'vertigo &amp; rest',
        'Approver' => 'For Approval',
        'Manager' => 'atheena@digitalfeet.com',
        'Status' => 'Approved',
        'HR' => 'false',
        'Remarks' => '[atheena@digitalfeet.com]',
        'ApprovedBy' => 'nico@digitalfeet.com',
        'OverallStatus' => 'Approved',
        'LeavesID' => '4',
        'Created By' => 'Vevien',
        'Approval status' => '0',
    ], $overrides));
}

it('imports leave rows, resolving emails and mapping the leave type', function () {
    $employee = User::factory()->create(['email' => 'vevien@digitalfeet.com']);
    $manager = User::factory()->create(['email' => 'atheena@digitalfeet.com']);
    $approver = User::factory()->create(['email' => 'nico@digitalfeet.com']);

    $path = writeTimeoffCsv([timeoffRow(['HR' => 'true'])]);

    $this->artisan('leave:import-sharepoint', ['path' => $path])->assertSuccessful();

    $record = LeaveRequest::where('sharepoint_id', 3773)->sole();

    expect($record->user_id)->toBe($employee->id)
        ->and($record->manager_id)->toBe($manager->id)
        ->and($record->approved_by)->toBe($approver->id)
        ->and($record->request_type)->toBe(LeaveType::SICK)
        ->and($record->start_date->toDateString())->toBe('2026-07-21')
        ->and($record->end_date->toDateString())->toBe('2026-07-21')
        ->and($record->start_time)->toBe('10:00')
        ->and($record->end_time)->toBe('12:00')
        ->and($record->reason)->toBe('vertigo & rest')
        ->and($record->status)->toBe(AttendanceStatus::APPROVED)
        ->and($record->manager_status)->toBe(AttendanceStatus::APPROVED->value)
        // Not cast on the model, so the boolean column comes back as 1/0.
        ->and((bool) $record->hr_approved)->toBeTrue()
        // "Filed" (created_at) must reflect the SharePoint Created date.
        ->and($record->created_at->format('Y-m-d H:i'))->toBe('2026-07-20 19:07');

    @unlink($path);
});

it('imports DF Sportsfest rows as vacation leave', function () {
    User::factory()->create(['email' => 'vevien@digitalfeet.com']);

    $path = writeTimeoffCsv([timeoffRow(['ID' => '900', 'Title' => 'DF Sportsfest'])]);

    $this->artisan('leave:import-sharepoint', ['path' => $path])->assertSuccessful();

    expect(LeaveRequest::where('sharepoint_id', 900)->value('request_type'))
        ->toBe(LeaveType::VACATION);

    @unlink($path);
});

it('skips rows whose employee email has no matching user', function () {
    User::factory()->create(['email' => 'vevien@digitalfeet.com']);

    $path = writeTimeoffCsv([
        timeoffRow(['ID' => '10', 'Email' => 'vevien@digitalfeet.com']),
        timeoffRow(['ID' => '11', 'Email' => 'ghost@digitalfeet.com']),
    ]);

    $this->artisan('leave:import-sharepoint', ['path' => $path])
        ->expectsOutputToContain('no user for "ghost@digitalfeet.com"')
        ->assertSuccessful();

    expect(LeaveRequest::count())->toBe(1)
        ->and(LeaveRequest::where('sharepoint_id', 11)->exists())->toBeFalse();

    @unlink($path);
});

it('is idempotent when re-run on the same list', function () {
    User::factory()->create(['email' => 'vevien@digitalfeet.com']);

    $path = writeTimeoffCsv([timeoffRow(['ID' => '7'])]);

    $this->artisan('leave:import-sharepoint', ['path' => $path])->assertSuccessful();
    $this->artisan('leave:import-sharepoint', ['path' => $path])->assertSuccessful();

    expect(LeaveRequest::where('sharepoint_id', 7)->count())->toBe(1);

    @unlink($path);
});

it('does not write anything on a dry run', function () {
    User::factory()->create(['email' => 'vevien@digitalfeet.com']);

    $path = writeTimeoffCsv([timeoffRow()]);

    $this->artisan('leave:import-sharepoint', ['path' => $path, '--dry-run' => true])
        ->expectsOutputToContain('Would import 1')
        ->assertSuccessful();

    expect(LeaveRequest::count())->toBe(0);

    @unlink($path);
});
