<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

const IPHONE_UA = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1';

it('shows the welcome page to guests', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('welcome'));
});

it('sends a signed-in employee to the mobile app', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('home'))->assertRedirect(route('mobile.home'));
});

it('sends a signed-in manager on desktop to the dashboard', function () {
    Role::findOrCreate('hr', 'web');
    $user = User::factory()->create();
    $user->assignRole('hr');
    $this->actingAs($user);

    $this->get(route('home'))->assertRedirect(route('dashboard'));
});

it('sends a manager on a phone to the mobile app', function () {
    Role::findOrCreate('hr', 'web');
    $user = User::factory()->create();
    $user->assignRole('hr');
    $this->actingAs($user);

    $this->withHeader('User-Agent', IPHONE_UA)
        ->get(route('home'))
        ->assertRedirect(route('mobile.home'));
});
