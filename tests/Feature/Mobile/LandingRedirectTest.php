<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('shows the welcome page to guests', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('welcome'));
});

it('sends a signed-in desktop visitor to the admin panel', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('home'))->assertRedirect('/admin');
});

it('sends a signed-in phone visitor to the mobile app', function () {
    $this->actingAs(User::factory()->create());

    $this->withHeader('User-Agent', iphoneUa())
        ->get(route('home'))
        ->assertRedirect(route('mobile.home'));
});
