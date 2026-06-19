<?php

use App\Enum\UserStatus;
use App\Models\User;
use App\Services\CelebrationService;

beforeEach(function () {
    $this->travelTo('2026-06-19');
    $this->service = new CelebrationService;
});

it('finds employees with a birthday today', function () {
    $birthday = User::factory()->create(['birthday' => '1990-06-19']);
    User::factory()->create(['birthday' => '1990-06-20']); // tomorrow
    User::factory()->create(['birthday' => null]);

    $today = $this->service->birthdaysToday();

    expect($today)->toHaveCount(1)
        ->and($today->first()->is($birthday))->toBeTrue();
});

it('finds employees with a work anniversary today and computes years', function () {
    $hired = User::factory()->create(['date_hired' => '2022-06-19']);
    User::factory()->create(['date_hired' => '2026-06-19']); // hired today, 0 years
    User::factory()->create(['date_hired' => '2022-06-18']); // yesterday

    $today = $this->service->anniversariesToday();

    expect($today)->toHaveCount(1)
        ->and($today->first()['user']->is($hired))->toBeTrue()
        ->and($today->first()['years'])->toBe(4);
});

it('ignores inactive employees', function () {
    User::factory()->create([
        'birthday' => '1990-06-19',
        'status' => UserStatus::INACTIVE->value,
    ]);

    expect($this->service->birthdaysToday())->toBeEmpty();
});

it('returns a birthday greeting for the celebrant', function () {
    $user = User::factory()->create(['birthday' => '1990-06-19']);

    expect($this->service->celebrationFor($user))
        ->type->toBe('birthday')
        ->emoji->toBe('🎂');
});

it('returns an anniversary greeting for the celebrant', function () {
    $user = User::factory()->create(['date_hired' => '2020-06-19', 'birthday' => null]);

    $celebration = $this->service->celebrationFor($user);

    expect($celebration)->type->toBe('anniversary')
        ->and($celebration['message'])->toContain('6 years');
});

it('returns null when there is nothing to celebrate', function () {
    $user = User::factory()->create(['birthday' => '1990-01-01', 'date_hired' => '2020-01-01']);

    expect($this->service->celebrationFor($user))->toBeNull();
});
