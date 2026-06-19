<?php

use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;

beforeEach(function () {
    $this->travelTo('2026-06-19');
});

it('notifies every active employee when someone is celebrating', function () {
    User::factory()->create(['birthday' => '1990-06-19']); // birthday today
    User::factory()->count(2)->create(['birthday' => null]);

    $this->artisan('app:notify-celebrations')->assertSuccessful();

    // One database notification per active employee (3 in total).
    expect(DatabaseNotification::query()->count())->toBe(3);
});

it('sends nothing when there are no celebrations today', function () {
    User::factory()->count(3)->create(['birthday' => '1990-01-01', 'date_hired' => '2020-01-01']);

    $this->artisan('app:notify-celebrations')
        ->expectsOutputToContain('No celebrations today.')
        ->assertSuccessful();

    expect(DatabaseNotification::query()->count())->toBe(0);
});
