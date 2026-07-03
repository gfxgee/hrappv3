<?php

use App\Enum\AttendanceStatus;
use App\Models\OverTimeRequest;
use App\Models\User;

it('files an overtime request for approval', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->post(route('mobile.overtime.store'), [
        'request_date' => '2026-07-06',
        'hours' => 2,
        'reason' => 'Finished the month-end report.',
    ])->assertSessionHasNoErrors();

    $overtime = OverTimeRequest::query()->where('user_id', $user->id)->sole();

    expect((float) $overtime->hours)->toBe(2.0)
        ->and($overtime->status)->toBe(AttendanceStatus::FOR_APPROVAL);
});

it('rejects overtime below the minimum of half an hour', function () {
    $this->actingAs(User::factory()->create());

    $this->post(route('mobile.overtime.store'), [
        'request_date' => '2026-07-06',
        'hours' => 0.2,
        'reason' => 'Too short.',
    ])->assertSessionHasErrors('hours');

    expect(OverTimeRequest::query()->count())->toBe(0);
});
