<?php

use App\Enum\AttendanceStatus;
use App\Enum\LeaveType;
use App\Models\LeaveRequest;
use App\Models\OnCallMember;
use App\Models\User;
use Carbon\CarbonImmutable;

beforeEach(function () {
    // Pin "today" to a Monday (a working day) so the command runs deterministically.
    $this->travelTo(CarbonImmutable::parse('2026-08-03'));
});

it('notifies the stand-in when the owner is on leave today', function () {
    $owner = User::factory()->create(['name' => 'Owner', 'status' => 'active']);
    $standIn = User::factory()->create(['name' => 'Stand In', 'status' => 'active']);
    OnCallMember::create(['user_id' => $owner->id, 'position' => 1]);
    OnCallMember::create(['user_id' => $standIn->id, 'position' => 2]);

    LeaveRequest::factory()->create([
        'user_id' => $owner->id,
        'request_type' => LeaveType::VACATION,
        'status' => AttendanceStatus::APPROVED,
        'start_date' => today(),
        'end_date' => today(),
    ]);

    $this->artisan('on-call:notify-standin')->assertSuccessful();

    expect($standIn->fresh()->notifications()->count())->toBe(1)
        ->and($owner->fresh()->notifications()->count())->toBe(0);
});

it('does not notify when the owner is present today', function () {
    $owner = User::factory()->create(['status' => 'active']);
    $standIn = User::factory()->create(['status' => 'active']);
    OnCallMember::create(['user_id' => $owner->id, 'position' => 1]);
    OnCallMember::create(['user_id' => $standIn->id, 'position' => 2]);

    $this->artisan('on-call:notify-standin')->assertSuccessful();

    expect($owner->fresh()->notifications()->count())->toBe(0)
        ->and($standIn->fresh()->notifications()->count())->toBe(0);
});
