<?php

use App\Enum\AttendanceStatus;
use App\Enum\LeaveType;
use App\Models\LeaveRequest;
use App\Models\User;

it('returns name, reason, and time duration of everyone on leave today', function () {
    $user = User::factory()->create(['name' => 'Jane Doe']);
    LeaveRequest::factory()->create([
        'user_id' => $user->id,
        'request_type' => LeaveType::VACATION,
        'status' => AttendanceStatus::APPROVED,
        'start_date' => today()->subDay(),
        'end_date' => today()->addDay(),
        'start_time' => '09:00',
        'end_time' => '13:00',
        'reason' => 'Family trip',
    ]);

    $this->getJson(route('api.leaves.today'))
        ->assertOk()
        ->assertExactJson([
            [
                'name' => 'Jane Doe',
                'reason' => 'Family trip',
                'start_time' => '09:00',
                'end_time' => '13:00',
                'duration_hours' => 4,
            ],
        ]);
});

it('returns null duration when the leave has no times', function () {
    LeaveRequest::factory()->create([
        'status' => AttendanceStatus::APPROVED,
        'start_date' => today()->subDay(),
        'end_date' => today()->addDay(),
        'start_time' => null,
        'end_time' => null,
    ]);

    $this->getJson(route('api.leaves.today'))
        ->assertOk()
        ->assertJsonPath('0.start_time', null)
        ->assertJsonPath('0.end_time', null)
        ->assertJsonPath('0.duration_hours', null);
});

it('excludes past, future, cancelled, rejected, and WFH leaves', function () {
    LeaveRequest::factory()->create([
        'start_date' => today()->subDays(10),
        'end_date' => today()->subDays(5),
        'status' => AttendanceStatus::APPROVED,
    ]);
    LeaveRequest::factory()->create([
        'start_date' => today()->addDays(5),
        'end_date' => today()->addDays(10),
        'status' => AttendanceStatus::APPROVED,
    ]);
    LeaveRequest::factory()->create([
        'start_date' => today()->subDay(),
        'end_date' => today()->addDay(),
        'status' => AttendanceStatus::CANCELLED,
    ]);
    LeaveRequest::factory()->create([
        'request_type' => LeaveType::WFH,
        'start_date' => today()->subDay(),
        'end_date' => today()->addDay(),
        'status' => AttendanceStatus::APPROVED,
    ]);

    $this->getJson(route('api.leaves.today'))
        ->assertOk()
        ->assertExactJson([]);
});
