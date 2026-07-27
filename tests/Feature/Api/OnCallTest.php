<?php

use App\Enum\AttendanceStatus;
use App\Enum\LeaveType;
use App\Models\LeaveRequest;
use App\Models\OnCallMember;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

it('returns the current on-call developer and week range', function () {
    $user = User::factory()->create(['name' => 'On Call Dev', 'status' => 'active']);
    OnCallMember::create(['user_id' => $user->id, 'position' => 1]);

    $weekStart = CarbonImmutable::parse(today())->startOfWeek(CarbonInterface::MONDAY);

    $this->getJson(route('api.on-call.current'))
        ->assertOk()
        ->assertJsonPath('name', 'On Call Dev')
        ->assertJsonPath('week_start', $weekStart->toDateString())
        ->assertJsonPath('week_end', $weekStart->endOfWeek(CarbonInterface::SUNDAY)->toDateString());
});

it('resolves on-call for a specific date via ?date', function () {
    $user = User::factory()->create(['name' => 'Any Week Dev', 'status' => 'active']);
    OnCallMember::create(['user_id' => $user->id, 'position' => 1]);

    $target = '2026-08-05'; // a Wednesday
    $weekStart = CarbonImmutable::parse($target)->startOfWeek(CarbonInterface::MONDAY);

    $this->getJson(route('api.on-call.current', ['date' => $target]))
        ->assertOk()
        ->assertJsonPath('name', 'Any Week Dev')
        ->assertJsonPath('week_start', $weekStart->toDateString());
});

it('returns a null name when the roster is empty', function () {
    $this->getJson(route('api.on-call.current'))
        ->assertOk()
        ->assertJsonPath('name', null);
});

it('returns today\'s effective on-call, with a stand-in when the owner is out', function () {
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

    $this->getJson(route('api.on-call.today'))
        ->assertOk()
        ->assertJsonPath('name', 'Stand In')
        ->assertJsonPath('is_substitute', true)
        ->assertJsonPath('covering_for', 'Owner');
});
