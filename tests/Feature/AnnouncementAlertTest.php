<?php

use App\Models\Announcement;
use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;

it('notifies every active employee when an urgent alert is created', function () {
    User::factory()->count(3)->create();

    Announcement::factory()->urgent()->create(['title' => 'Typhoon — office closed']);

    expect(DatabaseNotification::query()->count())->toBe(3);
});

it('does not notify for a non-urgent announcement', function () {
    User::factory()->count(3)->create();

    Announcement::factory()->create();

    expect(DatabaseNotification::query()->count())->toBe(0);
});

it('does not notify for an inactive urgent alert', function () {
    User::factory()->count(2)->create();

    Announcement::factory()->urgent()->inactive()->create();

    expect(DatabaseNotification::query()->count())->toBe(0);
});

it('notifies when an existing announcement is toggled urgent', function () {
    User::factory()->count(2)->create();
    $announcement = Announcement::factory()->create();

    expect(DatabaseNotification::query()->count())->toBe(0);

    $announcement->update(['is_urgent' => true]);

    expect(DatabaseNotification::query()->count())->toBe(2);
});

it('does not re-notify on an unrelated edit of an urgent alert', function () {
    User::factory()->count(2)->create();
    $announcement = Announcement::factory()->urgent()->create();

    expect(DatabaseNotification::query()->count())->toBe(2);

    $announcement->update(['title' => 'Updated title']);

    // Still only the original batch — no duplicate notifications.
    expect(DatabaseNotification::query()->count())->toBe(2);
});

it('renders urgent alerts prominently and without a dismiss button', function () {
    $this->actingAs(User::factory()->create(['status' => 'active']));

    Announcement::factory()->urgent()->create([
        'title' => 'Typhoon warning',
        'message' => '<p>Work from home tomorrow.</p>',
    ]);

    $html = view('filament.announcements.banners')->render();

    expect($html)->toContain('Urgent alert')
        ->toContain('Typhoon warning')
        ->toContain('Work from home tomorrow')
        ->toContain('animate-pulse')
        ->not->toContain('Dismiss');
});
