<?php

use App\Enum\AttendanceStatus;
use App\Enum\LeaveType;
use App\Models\LeaveRequest;
use App\Models\User;

it('files a leave request for approval', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->post(route('mobile.leave.store'), [
        'request_type' => LeaveType::WFH->value,
        'start_date' => '2026-07-06',
        'end_date' => '2026-07-06',
        'reason' => 'Working from home to focus.',
    ])->assertSessionHasNoErrors();

    $leave = LeaveRequest::query()->where('user_id', $user->id)->sole();

    expect($leave->request_type)->toBe(LeaveType::WFH)
        ->and($leave->status)->toBe(AttendanceStatus::FOR_APPROVAL);
});

it('requires a reason', function () {
    $this->actingAs(User::factory()->create());

    $this->post(route('mobile.leave.store'), [
        'request_type' => LeaveType::WFH->value,
        'start_date' => '2026-07-06',
        'end_date' => '2026-07-06',
    ])->assertSessionHasErrors('reason');

    expect(LeaveRequest::query()->count())->toBe(0);
});

it('blocks a leave request that exceeds the remaining balance', function () {
    $user = User::factory()->create();
    $user->userData()->create(['vacation_leave' => 1]);
    $this->actingAs($user);

    // Mon–Fri is five working days, well over the one-day balance.
    $this->post(route('mobile.leave.store'), [
        'request_type' => LeaveType::VACATION->value,
        'start_date' => '2026-07-06',
        'end_date' => '2026-07-10',
        'reason' => 'Trip.',
    ])->assertSessionHasErrors('end_date');

    expect(LeaveRequest::query()->count())->toBe(0);
});
