<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('renders the team screen for a signed-in user', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('mobile.team'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('mobile/team')->has('members'));
});
