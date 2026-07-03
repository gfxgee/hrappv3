<?php

use App\Models\User;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

function makeNotification(User $user, ?string $readAt = null): void
{
    $user->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => 'App\\Notifications\\Test',
        'data' => ['title' => 'Leave approved', 'body' => 'Your vacation leave was approved.'],
        'read_at' => $readAt,
    ]);
}

it('lists the employee\'s alerts with an unread count', function () {
    $user = User::factory()->create();
    makeNotification($user);
    makeNotification($user, now()->toDateTimeString());
    $this->actingAs($user);

    $this->get(route('mobile.alerts'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('mobile/alerts')
            ->where('unread', 1)
            ->has('alerts', 2)
        );
});

it('marks all alerts as read', function () {
    $user = User::factory()->create();
    makeNotification($user);
    makeNotification($user);
    $this->actingAs($user);

    expect($user->unreadNotifications()->count())->toBe(2);

    $this->post(route('mobile.alerts.read'));

    expect($user->refresh()->unreadNotifications()->count())->toBe(0);
});
