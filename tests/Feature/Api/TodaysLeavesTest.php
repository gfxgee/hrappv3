<?php

use App\Enum\AttendanceStatus;
use App\Enum\LeaveType;
use App\Models\LeaveRequest;
use App\Models\User;

it('returns name and reason of everyone on leave today', function () {
    $user = User::factory()->create(['name' => 'Jane Doe']);
    LeaveRequest::factory()->create([
        'user_id' => $user->id,
        'request_type' => LeaveType::VACATION,
        'status' => AttendanceStatus::APPROVED,
        'start_date' => today()->subDay(),
        'end_date' => today()->addDay(),
        'reason' => 'Family trip',
    ]);

    $this->getJson(route('api.leaves.today'))
        ->assertOk()
        ->assertExactJson([
            ['name' => 'Jane Doe', 'reason' => 'Family trip'],
        ]);
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
