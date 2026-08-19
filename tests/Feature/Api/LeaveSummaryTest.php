<?php

use App\Enum\AttendanceStatus;
use App\Enum\LeaveType;
use App\Models\Department;
use App\Models\LeaveRequest;
use App\Models\OverTimeRequest;
use App\Models\User;

it('summarises leave and overtime per active employee for the range', function () {
    $department = Department::factory()->create(['name' => 'Development']);
    $user = User::factory()->create([
        'name' => 'Payroll Person',
        'display_name' => 'Pay',
        'status' => 'active',
        'department_id' => $department->id,
    ]);

    // Mon 2026-08-03 → Wed 2026-08-05 = 3 working days of LWOP.
    LeaveRequest::factory()->create([
        'user_id' => $user->id,
        'request_type' => LeaveType::LWOP,
        'status' => AttendanceStatus::APPROVED,
        'start_date' => '2026-08-03',
        'end_date' => '2026-08-05',
    ]);
    // A single sick day.
    LeaveRequest::factory()->create([
        'user_id' => $user->id,
        'request_type' => LeaveType::SICK,
        'status' => AttendanceStatus::APPROVED_AND_VERIFIED,
        'start_date' => '2026-08-06',
        'end_date' => '2026-08-06',
    ]);
    OverTimeRequest::factory()->create([
        'user_id' => $user->id,
        'status' => AttendanceStatus::APPROVED,
        'request_date' => '2026-08-04',
        'hours' => 2.5,
    ]);

    $response = $this->getJson(route('api.reports.leave-summary', [
        'start' => '2026-08-01',
        'end' => '2026-08-31',
    ]))->assertOk();

    expect($response->json('employee_count'))->toBe(1)
        ->and($response->json('start_date'))->toBe('2026-08-01')
        ->and($response->json('end_date'))->toBe('2026-08-31')
        ->and($response->json('employees.0.name'))->toBe('Payroll Person')
        ->and($response->json('employees.0.display_name'))->toBe('Pay')
        ->and($response->json('employees.0.department'))->toBe('Development')
        ->and($response->json('employees.0.leaves.lwop.days'))->toEqual(3)
        ->and($response->json('employees.0.leaves.lwop.requests'))->toBe(1)
        ->and($response->json('employees.0.leaves.sick.days'))->toEqual(1)
        ->and($response->json('employees.0.total_leave_days'))->toEqual(4)
        ->and($response->json('employees.0.overtime_hours'))->toEqual(2.5)
        ->and($response->json('employees.0.overtime_requests'))->toBe(1);
});

it('counts only the days that fall inside the range', function () {
    $user = User::factory()->create(['status' => 'active']);

    // Fri Jul 31 → Tue Aug 4. Querying August must count only Aug 3 + Aug 4
    // (Aug 1–2 is a weekend, Jul 31 is outside the range).
    LeaveRequest::factory()->create([
        'user_id' => $user->id,
        'request_type' => LeaveType::VACATION,
        'status' => AttendanceStatus::APPROVED,
        'start_date' => '2026-07-31',
        'end_date' => '2026-08-04',
    ]);

    $response = $this->getJson(route('api.reports.leave-summary', [
        'start' => '2026-08-01',
        'end' => '2026-08-31',
    ]))->assertOk();

    expect($response->json('employees.0.leaves.vacation.days'))->toEqual(2);
});

it('excludes inactive employees but still lists active ones with nothing', function () {
    User::factory()->create(['name' => 'Active Nobody', 'status' => 'active']);
    User::factory()->create(['name' => 'Inactive Person', 'status' => 'inactive']);

    $response = $this->getJson(route('api.reports.leave-summary', [
        'start' => '2026-08-01',
        'end' => '2026-08-31',
    ]))->assertOk();

    expect($response->json('employee_count'))->toBe(1)
        ->and($response->json('employees.0.name'))->toBe('Active Nobody')
        // Every type key is present and zeroed, so the automation can index safely.
        ->and($response->json('employees.0.leaves.sick.days'))->toEqual(0)
        ->and($response->json('employees.0.total_leave_days'))->toEqual(0)
        ->and($response->json('employees.0.overtime_hours'))->toEqual(0);
});

it('ignores pending, rejected, and cancelled records', function () {
    $user = User::factory()->create(['status' => 'active']);

    foreach ([AttendanceStatus::FOR_APPROVAL, AttendanceStatus::REJECTED, AttendanceStatus::CANCELLED] as $status) {
        LeaveRequest::factory()->create([
            'user_id' => $user->id,
            'request_type' => LeaveType::SICK,
            'status' => $status,
            'start_date' => '2026-08-04',
            'end_date' => '2026-08-04',
        ]);
        OverTimeRequest::factory()->create([
            'user_id' => $user->id,
            'status' => $status,
            'request_date' => '2026-08-04',
            'hours' => 3,
        ]);
    }

    $response = $this->getJson(route('api.reports.leave-summary', [
        'start' => '2026-08-01',
        'end' => '2026-08-31',
    ]))->assertOk();

    expect($response->json('employees.0.leaves.sick.days'))->toEqual(0)
        ->and($response->json('employees.0.overtime_hours'))->toEqual(0);
});

it('counts a partial-day leave as a fraction', function () {
    $user = User::factory()->create(['status' => 'active']);
    $user->userData()->create(['time_in' => '10:00', 'time_out' => '18:00']);

    // 10:00–13:00 on a working Tuesday = 3h of an 8h day.
    LeaveRequest::factory()->create([
        'user_id' => $user->id,
        'request_type' => LeaveType::EMERGENCY,
        'status' => AttendanceStatus::APPROVED,
        'start_date' => '2026-08-04',
        'end_date' => '2026-08-04',
        'start_time' => '10:00',
        'end_time' => '13:00',
    ]);

    $response = $this->getJson(route('api.reports.leave-summary', [
        'start' => '2026-08-01',
        'end' => '2026-08-31',
    ]))->assertOk();

    expect($response->json('employees.0.leaves.emergency.days'))->toEqual(0.38);
});

it('defaults to the current month and tolerates a reversed range', function () {
    User::factory()->create(['status' => 'active']);

    $this->getJson(route('api.reports.leave-summary'))
        ->assertOk()
        ->assertJsonPath('start_date', today()->startOfMonth()->toDateString())
        ->assertJsonPath('end_date', today()->endOfMonth()->toDateString());

    $this->getJson(route('api.reports.leave-summary', ['start' => '2026-08-31', 'end' => '2026-08-01']))
        ->assertOk()
        ->assertJsonPath('start_date', '2026-08-01')
        ->assertJsonPath('end_date', '2026-08-31');
});

it('itemises LWOP entries with date, hours, and reason', function () {
    $user = User::factory()->create(['status' => 'active']);
    $user->userData()->create(['time_in' => '10:00', 'time_out' => '18:00']);

    // A full day...
    LeaveRequest::factory()->create([
        'user_id' => $user->id,
        'request_type' => LeaveType::LWOP,
        'status' => AttendanceStatus::APPROVED,
        'start_date' => '2026-08-06',
        'end_date' => '2026-08-06',
        'reason' => 'high fever, need to rest',
    ]);
    // ...and an earlier partial day, to prove ordering and hour maths.
    LeaveRequest::factory()->create([
        'user_id' => $user->id,
        'request_type' => LeaveType::LWOP,
        'status' => AttendanceStatus::APPROVED,
        'start_date' => '2026-08-04',
        'end_date' => '2026-08-04',
        'start_time' => '10:00',
        'end_time' => '13:00',
        'reason' => 'Mild headache, need to rest more',
    ]);

    $response = $this->getJson(route('api.reports.leave-summary', [
        'start' => '2026-08-01', 'end' => '2026-08-31',
    ]))->assertOk();

    $entries = $response->json('employees.0.leaves.lwop.entries');

    expect($entries)->toHaveCount(2)
        // Chronological, like the payslip table.
        ->and($entries[0]['date'])->toBe('2026-08-04')
        ->and($entries[0]['hours'])->toEqual(3)
        ->and($entries[0]['reason'])->toBe('Mild headache, need to rest more')
        ->and($entries[1]['date'])->toBe('2026-08-06')
        ->and($entries[1]['hours'])->toEqual(8)
        ->and($entries[1]['reason'])->toBe('high fever, need to rest')
        // Bucket totals still line up with the entries.
        ->and($response->json('employees.0.leaves.lwop.hours'))->toEqual(11)
        ->and($response->json('employees.0.leaves.lwop.requests'))->toBe(2);
});

it('itemises overtime entries with date, hours, and reason', function () {
    $user = User::factory()->create(['status' => 'active']);

    OverTimeRequest::factory()->create([
        'user_id' => $user->id,
        'status' => AttendanceStatus::APPROVED,
        'request_date' => '2026-08-08',
        'hours' => 1,
        'reason' => 'streams',
    ]);
    OverTimeRequest::factory()->create([
        'user_id' => $user->id,
        'status' => AttendanceStatus::APPROVED,
        'request_date' => '2026-08-07',
        'hours' => 0.5,
        'reason' => 'client tasks and streams',
    ]);

    $response = $this->getJson(route('api.reports.leave-summary', [
        'start' => '2026-08-01', 'end' => '2026-08-31',
    ]))->assertOk();

    $entries = $response->json('employees.0.overtime_entries');

    expect($entries)->toHaveCount(2)
        ->and($entries[0]['date'])->toBe('2026-08-07')
        ->and($entries[0]['hours'])->toEqual(0.5)
        ->and($entries[0]['reason'])->toBe('client tasks and streams')
        ->and($entries[1]['date'])->toBe('2026-08-08')
        ->and($response->json('employees.0.overtime_hours'))->toEqual(1.5);
});

it('keeps entries as empty arrays when there is nothing in the range', function () {
    User::factory()->create(['status' => 'active']);

    $response = $this->getJson(route('api.reports.leave-summary', [
        'start' => '2026-08-01', 'end' => '2026-08-31',
    ]))->assertOk();

    expect($response->json('employees.0.leaves.lwop.entries'))->toBe([])
        ->and($response->json('employees.0.overtime_entries'))->toBe([])
        ->and($response->json('employees.0.total_leave_hours'))->toEqual(0);
});

it('clips an itemised entry to the range boundary', function () {
    $user = User::factory()->create(['status' => 'active']);

    // Fri Jul 31 -> Tue Aug 4; August should show Aug 3-4 only.
    LeaveRequest::factory()->create([
        'user_id' => $user->id,
        'request_type' => LeaveType::LWOP,
        'status' => AttendanceStatus::APPROVED,
        'start_date' => '2026-07-31',
        'end_date' => '2026-08-04',
        'reason' => 'extended unpaid leave',
    ]);

    $response = $this->getJson(route('api.reports.leave-summary', [
        'start' => '2026-08-01', 'end' => '2026-08-31',
    ]))->assertOk();

    $entry = $response->json('employees.0.leaves.lwop.entries.0');

    expect($entry['date'])->toBe('2026-08-03')   // clipped start (Aug 1-2 is a weekend)
        ->and($entry['end_date'])->toBe('2026-08-04')
        ->and($entry['days'])->toEqual(2)
        ->and($entry['hours'])->toEqual(16);
});
