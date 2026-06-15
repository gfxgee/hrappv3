<?php

use App\Models\Announcement;
use App\Models\Department;
use App\Models\User;

it('shows active, open-ended announcements to everyone', function () {
    $announcement = Announcement::factory()->create(['title' => 'Hello team']);

    $ids = Announcement::liveForUser(User::factory()->create())->pluck('id');

    expect($ids)->toContain($announcement->id);
});

it('hides inactive, not-yet-started, and expired announcements', function () {
    $inactive = Announcement::factory()->inactive()->create();
    $future = Announcement::factory()->create(['starts_at' => now()->addDay()]);
    $expired = Announcement::factory()->create(['ends_at' => now()->subDay()]);
    $live = Announcement::factory()->create([
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addDay(),
    ]);

    $ids = Announcement::liveForUser(User::factory()->create())->pluck('id');

    expect($ids)->toContain($live->id)
        ->not->toContain($inactive->id)
        ->not->toContain($future->id)
        ->not->toContain($expired->id);
});

it('only shows department-targeted announcements to members of that department', function () {
    $sales = Department::factory()->create();
    $engineering = Department::factory()->create();

    $targeted = Announcement::factory()->create();
    $targeted->departments()->attach($sales);

    $everyone = Announcement::factory()->create();

    $salesUser = User::factory()->create(['department_id' => $sales->id]);
    $engUser = User::factory()->create(['department_id' => $engineering->id]);
    $noDeptUser = User::factory()->create(['department_id' => null]);

    expect(Announcement::liveForUser($salesUser)->pluck('id'))
        ->toContain($targeted->id)
        ->toContain($everyone->id);

    expect(Announcement::liveForUser($engUser)->pluck('id'))
        ->not->toContain($targeted->id)
        ->toContain($everyone->id);

    // A user with no department still sees the everyone announcement, not the targeted one.
    expect(Announcement::liveForUser($noDeptUser)->pluck('id'))
        ->not->toContain($targeted->id)
        ->toContain($everyone->id);
});

it('renders live announcement content in the banner view for an authenticated user', function () {
    $user = User::factory()->create(['status' => 'active']);
    $this->actingAs($user);

    Announcement::factory()->create([
        'title' => 'Payday moved',
        'message' => '<p>Salaries land on the 14th this month.</p>',
    ]);

    $html = view('filament.announcements.banners')->render();

    expect($html)->toContain('Payday moved')
        ->toContain('Salaries land on the 14th');
});

it('renders nothing when there are no live announcements', function () {
    $this->actingAs(User::factory()->create(['status' => 'active']));

    Announcement::factory()->inactive()->create(['title' => 'Hidden notice']);

    $html = view('filament.announcements.banners')->render();

    expect(trim($html))->toBe('')
        ->and($html)->not->toContain('Hidden notice');
});
