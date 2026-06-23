<?php

use App\Enum\Mood;
use App\Filament\Widgets\MoodCheckInWidget;
use App\Models\MoodCheckIn;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel('admin');
});

it('logs a mood check-in for the authenticated user', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::test(MoodCheckInWidget::class)
        ->call('logMood', Mood::HAPPY->value)
        ->assertDispatched('mood-logged');

    $checkIn = MoodCheckIn::query()->firstOrFail();

    expect($checkIn->user_id)->toBe($user->id)
        ->and($checkIn->mood)->toBe(Mood::HAPPY)
        ->and($checkIn->logged_on->isToday())->toBeTrue();
});

it('updates today\'s check-in instead of creating a duplicate', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::test(MoodCheckInWidget::class)
        ->call('logMood', Mood::STRESSED->value)
        ->call('logMood', Mood::CALM->value);

    expect(MoodCheckIn::query()->count())->toBe(1)
        ->and(MoodCheckIn::query()->first()->mood)->toBe(Mood::CALM);
});

it('ignores an invalid mood value', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(MoodCheckInWidget::class)
        ->call('logMood', 'ecstatic');

    expect(MoodCheckIn::query()->count())->toBe(0);
});

it('exposes the mood logged today', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    MoodCheckIn::factory()->for($user)->mood(Mood::STRESSED)->create();

    Livewire::test(MoodCheckInWidget::class)
        ->assertSuccessful();

    expect((new MoodCheckInWidget)->todaysMood())->toBe(Mood::STRESSED);
});
