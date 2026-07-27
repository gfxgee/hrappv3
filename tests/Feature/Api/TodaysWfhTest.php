<?php

use App\Enum\AttendanceStatus;
use App\Enum\LeaveType;
use App\Models\LeaveRequest;
use App\Models\User;

it('returns everyone working from home today', function () {
    $user = User::factory()->create(['name' => 'Home Worker']);
    LeaveRequest::factory()->create([
        'user_id' => $user->id,
        'request_type' => LeaveType::WFH,
        'status' => AttendanceStatus::APPROVED,
        'start_date' => today()->subDay(),
        'end_date' => today()->addDay(),
        'start_time' => '10:00',
        'end_time' => '18:00',
        'reason' => 'Focus day',
    ]);

    $this->getJson(route('api.leaves.wfh'))
        ->assertOk()
        ->assertExactJson([
            [
                'name' => 'Home Worker',
                'reason' => 'Focus day',
                'start_time' => '10:00',
                'end_time' => '18:00',
                'duration_hours' => 8,
            ],
        ]);
});

it('excludes non-WFH leaves from the WFH feed', function () {
    LeaveRequest::factory()->create([
        'request_type' => LeaveType::VACATION,
        'status' => AttendanceStatus::APPROVED,
        'start_date' => today(),
        'end_date' => today(),
    ]);

    $this->getJson(route('api.leaves.wfh'))
        ->assertOk()
        ->assertExactJson([]);
});

it('returns WFH for a specific date via the ?date override', function () {
    $target = today()->addDays(3)->toDateString();

    $user = User::factory()->create(['name' => 'Future WFH']);
    LeaveRequest::factory()->create([
        'user_id' => $user->id,
        'request_type' => LeaveType::WFH,
        'status' => AttendanceStatus::APPROVED,
        'start_date' => $target,
        'end_date' => $target,
    ]);

    $this->getJson(route('api.leaves.wfh', ['date' => $target]))
        ->assertOk()
        ->assertJsonCount(1)
        ->assertJsonPath('0.name', 'Future WFH');
});
