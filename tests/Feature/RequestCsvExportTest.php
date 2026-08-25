<?php

use App\Enum\AttendanceStatus;
use App\Enum\LeaveType;
use App\Models\AttendanceCorrectionRequest;
use App\Models\LeaveRequest;
use App\Models\OverTimeRequest;
use App\Models\User;
use App\Support\RequestCsvExport;

it('uses one shared column layout for every request type', function () {
    expect(RequestCsvExport::COLUMNS)->toBe([
        'ID', 'Created', 'Title', 'Date', 'Email', 'TimeStart', 'TimeEnd', 'Hrs', 'Reason',
        'EndDate', 'Name', 'Status',
    ]);
});

it('maps a leave request into the shared layout', function () {
    $user = User::factory()->create(['email' => 'jane@example.com']);
    $user->userData()->create(['time_in' => '10:00', 'time_out' => '18:00']);

    $leave = LeaveRequest::factory()->for($user)->create([
        'request_type' => LeaveType::SICK,
        'status' => AttendanceStatus::APPROVED,
        'start_date' => '2026-08-04',
        'end_date' => '2026-08-04',
        'start_time' => '10:00',
        'end_time' => '13:00',
        'reason' => 'Mild headache',
    ]);

    $row = RequestCsvExport::fromLeave($leave->fresh('user.userData'));

    expect($row[0])->toBe($leave->id)
        ->and($row[2])->toBe('Sick Leave')          // Title
        ->and($row[3])->toBe('2026-08-04')          // Date
        ->and($row[4])->toBe('jane@example.com')    // Email
        ->and($row[5])->toBe('10:00')               // TimeStart
        ->and($row[6])->toBe('13:00')               // TimeEnd
        ->and($row[7])->toEqual(3)                  // Hrs — 10:00-13:00 of an 8h day
        ->and($row[8])->toBe('Mild headache');
});

it('spreads a multi-day leave\'s hours across the whole range', function () {
    $user = User::factory()->create();
    $user->userData()->create(['time_in' => '10:00', 'time_out' => '18:00']);

    // Mon 2026-08-03 -> Wed 2026-08-05 = 3 working days = 24h.
    $leave = LeaveRequest::factory()->for($user)->create([
        'request_type' => LeaveType::LWOP,
        'status' => AttendanceStatus::APPROVED,
        'start_date' => '2026-08-03',
        'end_date' => '2026-08-05',
    ]);

    $row = RequestCsvExport::fromLeave($leave->fresh('user.userData'));

    expect($row[3])->toBe('2026-08-03')  // Date
        ->and($row[9])->toBe('2026-08-05') // EndDate
        ->and($row[7])->toEqual(24);       // Hrs
});

it('maps an overtime request into the shared layout', function () {
    $user = User::factory()->create(['email' => 'ot@example.com']);
    $ot = OverTimeRequest::factory()->for($user)->create([
        'request_date' => '2026-08-07',
        'hours' => 1.5,
        'reason' => 'streams',
        'status' => AttendanceStatus::APPROVED,
    ]);

    $row = RequestCsvExport::fromOvertime($ot->fresh('user'));

    expect($row[0])->toBe($ot->id)
        ->and($row[2])->toBe('Overtime')
        ->and($row[3])->toBe('2026-08-07')
        ->and($row[4])->toBe('ot@example.com')
        ->and($row[7])->toEqual(1.5)
        ->and($row[8])->toBe('streams');
});

it('maps an attendance correction into the shared layout', function () {
    $user = User::factory()->create(['email' => 'fix@example.com']);
    $correction = AttendanceCorrectionRequest::factory()->for($user)->create([
        'corrected_at' => '2026-08-10 09:15:00',
        'reason' => 'Forgot to clock in',
        'status' => AttendanceStatus::FOR_APPROVAL,
    ]);

    $row = RequestCsvExport::fromCorrection($correction->fresh('user'));

    expect($row[0])->toBe($correction->id)
        ->and($row[3])->toBe('2026-08-10')      // Date
        ->and($row[4])->toBe('fix@example.com')
        ->and($row[5])->toBe('09:15')           // TimeStart — the corrected punch
        ->and($row[8])->toBe('Forgot to clock in');
});
