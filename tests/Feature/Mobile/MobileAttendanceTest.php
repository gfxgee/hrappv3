<?php

use App\Enum\AttendanceCorrectionType;
use App\Enum\AttendanceStatus;
use App\Models\AttendanceCorrectionRequest;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('renders the attendance screen with the week and today', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('mobile.attendance'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('mobile/attendance')
            ->has('rows')
            ->has('today')
            ->has('correctionTypes')
        );
});

it('files an attendance correction for approval', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->post(route('mobile.attendance.correction'), [
        'correction_type' => AttendanceCorrectionType::MISSING_CLOCK_OUT->value,
        'corrected_at' => '2026-07-06 18:00:00',
        'reason' => 'Forgot to clock out.',
    ])->assertSessionHasNoErrors();

    $correction = AttendanceCorrectionRequest::query()->where('user_id', $user->id)->sole();

    expect($correction->correction_type)->toBe(AttendanceCorrectionType::MISSING_CLOCK_OUT)
        ->and($correction->status)->toBe(AttendanceStatus::FOR_APPROVAL);
});

it('requires the punch type when correcting a wrong time', function () {
    $this->actingAs(User::factory()->create());

    $this->post(route('mobile.attendance.correction'), [
        'correction_type' => AttendanceCorrectionType::WRONG_TIME->value,
        'corrected_at' => '2026-07-06 18:00:00',
        'reason' => 'Wrong time recorded.',
    ])->assertSessionHasErrors('target_log_type');

    expect(AttendanceCorrectionRequest::query()->count())->toBe(0);
});
