<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

it('redirects a regular employee from the dashboard to the mobile app', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('dashboard'))->assertRedirect(route('mobile.home'));
});

it('keeps HR/admins on the desktop dashboard', function () {
    Role::findOrCreate('hr', 'web');
    $user = User::factory()->create();
    $user->assignRole('hr');
    $this->actingAs($user);

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('dashboard'));
});

it('renders the team screen for an employee', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('mobile.team'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('mobile/team')->has('members'));
});
