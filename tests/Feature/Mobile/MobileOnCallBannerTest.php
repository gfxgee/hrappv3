<?php

use App\Enum\AttendanceStatus;
use App\Enum\LeaveType;
use App\Models\LeaveRequest;
use App\Models\OnCallMember;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

it('passes an on-call notice to the mobile home for the week\'s owner', function () {
    $owner = User::factory()->create(['status' => 'active']);
    OnCallMember::create(['user_id' => $owner->id, 'position' => 1]);

    $this->actingAs($owner)
        ->get(route('mobile.home'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('onCall.type', 'owner')
            ->where('onCall.covering_for', null));
});

it('passes a stand-in notice when covering for the owner today', function () {
    $owner = User::factory()->create(['name' => 'Owner', 'status' => 'active']);
    $standIn = User::factory()->create(['status' => 'active']);
    OnCallMember::create(['user_id' => $owner->id, 'position' => 1]);
    OnCallMember::create(['user_id' => $standIn->id, 'position' => 2]);

    LeaveRequest::factory()->create([
        'user_id' => $owner->id,
        'request_type' => LeaveType::VACATION,
        'status' => AttendanceStatus::APPROVED,
        'start_date' => today(),
        'end_date' => today(),
    ]);

    $this->actingAs($standIn)
        ->get(route('mobile.home'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('onCall.type', 'substitute')
            ->where('onCall.covering_for', 'Owner'));
});

it('sends a null on-call notice to everyone else', function () {
    $owner = User::factory()->create(['status' => 'active']);
    $other = User::factory()->create(['status' => 'active']);
    OnCallMember::create(['user_id' => $owner->id, 'position' => 1]);
    OnCallMember::create(['user_id' => $other->id, 'position' => 2]);

    $this->actingAs($other)
        ->get(route('mobile.home'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('onCall', null));
});
