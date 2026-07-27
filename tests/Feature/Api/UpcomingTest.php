<?php

use App\Enum\AttendanceStatus;
use App\Enum\LeaveType;
use App\Models\Holiday;
use App\Models\LeaveRequest;
use App\Models\User;

describe('upcoming leaves', function () {
    it('lists leaves starting within the window, soonest first', function () {
        $soon = User::factory()->create(['name' => 'Soon Later']);
        $later = User::factory()->create(['name' => 'Way Later']);

        LeaveRequest::factory()->create([
            'user_id' => $later->id,
            'request_type' => LeaveType::VACATION,
            'status' => AttendanceStatus::APPROVED,
            'start_date' => today()->addDays(10),
            'end_date' => today()->addDays(12),
            'reason' => 'Trip',
        ]);
        LeaveRequest::factory()->create([
            'user_id' => $soon->id,
            'request_type' => LeaveType::SICK,
            'status' => AttendanceStatus::FOR_APPROVAL,
            'start_date' => today()->addDays(3),
            'end_date' => today()->addDays(3),
            'reason' => 'Checkup',
        ]);

        $response = $this->getJson(route('api.upcoming.leaves', ['days' => 30]))
            ->assertOk()
            ->assertJsonCount(2);

        expect($response->json('0.name'))->toBe('Soon Later')
            ->and($response->json('0.days_until'))->toBe(3)
            ->and($response->json('0.type'))->toBe('Sick Leave')
            ->and($response->json('0.type_value'))->toBe('sick')
            ->and($response->json('1.name'))->toBe('Way Later');
    });

    it('excludes past, today, out-of-window, WFH, and cancelled/rejected leaves', function () {
        $base = [
            'status' => AttendanceStatus::APPROVED,
            'start_date' => today()->addDays(2),
            'end_date' => today()->addDays(2),
        ];

        LeaveRequest::factory()->create([...$base, 'start_date' => today()->subDays(3), 'end_date' => today()->subDay()]);
        LeaveRequest::factory()->create([...$base, 'start_date' => today(), 'end_date' => today()]);
        LeaveRequest::factory()->create([...$base, 'start_date' => today()->addDays(90)]);
        LeaveRequest::factory()->create([...$base, 'request_type' => LeaveType::WFH]);
        LeaveRequest::factory()->create([...$base, 'status' => AttendanceStatus::CANCELLED]);
        LeaveRequest::factory()->create([...$base, 'status' => AttendanceStatus::REJECTED]);

        $this->getJson(route('api.upcoming.leaves', ['days' => 30]))
            ->assertOk()
            ->assertExactJson([]);
    });
});

describe('upcoming birthdays', function () {
    it('lists active employees with a birthday within the window', function () {
        User::factory()->create(['name' => 'Birthday Soon', 'birthday' => today()->addDays(5)->format('1990-m-d')]);
        User::factory()->create(['name' => 'Birthday Far', 'birthday' => today()->addDays(90)->format('1990-m-d')]);
        User::factory()->create(['name' => 'No Birthday', 'birthday' => null]);

        $response = $this->getJson(route('api.upcoming.birthdays', ['days' => 30]))
            ->assertOk()
            ->assertJsonCount(1);

        expect($response->json('0.name'))->toBe('Birthday Soon')
            ->and($response->json('0.days_until'))->toBe(5);
    });
});

describe('upcoming holidays', function () {
    it('lists active holidays within the window, soonest first', function () {
        Holiday::factory()->create(['name' => 'Far Holiday', 'date' => today()->addDays(20)]);
        Holiday::factory()->create(['name' => 'Near Holiday', 'date' => today()->addDays(4), 'emoji' => '🎉']);
        Holiday::factory()->create(['name' => 'Inactive Holiday', 'date' => today()->addDays(2), 'is_active' => false]);
        Holiday::factory()->create(['name' => 'Out Of Window', 'date' => today()->addDays(90)]);

        $response = $this->getJson(route('api.upcoming.holidays', ['days' => 30]))
            ->assertOk()
            ->assertJsonCount(2);

        expect($response->json('0.name'))->toBe('Near Holiday')
            ->and($response->json('0.emoji'))->toBe('🎉')
            ->and($response->json('0.days_until'))->toBe(4)
            ->and($response->json('1.name'))->toBe('Far Holiday');
    });
});
