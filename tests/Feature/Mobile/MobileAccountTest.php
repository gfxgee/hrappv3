<?php

use App\Http\Middleware\TeamsEmbed;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('renders the account screen with the employee profile', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('mobile.account'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('mobile/account')
            ->has('name')
            ->has('email')
            ->has('department')
        );
});

it('leaves the sign-out button in place for an ordinary phone visit', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('mobile.account'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('teamsTab', false));
});

it('hides the sign-out button when the app is running inside a Teams tab', function () {
    // Signing out there is a dead end: the Microsoft popup is the only way
    // back in, and it cannot be reached from a signed-out tab.
    $this->actingAs(User::factory()->create());

    $this->withCookie(TeamsEmbed::MARKER, '1')
        ->get(route('mobile.account'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('teamsTab', true));
});
