<?php

use App\Models\OnCallAssignment;
use App\Models\OnCallMember;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

it('records the on-call assignment for the current week', function () {
    $user = User::factory()->create(['name' => 'Weekly Dev', 'status' => 'active']);
    OnCallMember::create(['user_id' => $user->id, 'position' => 1]);

    $this->artisan('on-call:assign')->assertSuccessful();

    $weekStart = CarbonImmutable::parse(today())->startOfWeek(CarbonInterface::MONDAY);

    expect(OnCallAssignment::whereDate('week_start', $weekStart)->value('user_id'))
        ->toBe($user->id)
        // The on-call developer is notified in-app.
        ->and($user->fresh()->notifications()->count())->toBe(1);
});

it('does not overwrite a manual override for the week', function () {
    $rostered = User::factory()->create(['status' => 'active']);
    $manual = User::factory()->create(['status' => 'active']);
    OnCallMember::create(['user_id' => $rostered->id, 'position' => 1]);

    $weekStart = CarbonImmutable::parse(today())->startOfWeek(CarbonInterface::MONDAY);
    OnCallAssignment::create([
        'week_start' => $weekStart,
        'user_id' => $manual->id,
        'is_override' => true,
    ]);

    $this->artisan('on-call:assign')->assertSuccessful();

    expect(OnCallAssignment::whereDate('week_start', $weekStart)->value('user_id'))
        ->toBe($manual->id)
        // A pre-existing override is not re-announced.
        ->and($manual->fresh()->notifications()->count())->toBe(0);
});
