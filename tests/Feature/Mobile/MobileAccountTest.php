<?php

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
