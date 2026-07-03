<?php

use App\Models\User;

test('guests are redirected to the login page', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

test('desktop users can visit the dashboard', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('dashboard'))->assertOk();
});

test('phone visitors are redirected from the dashboard to the mobile app', function () {
    $this->actingAs(User::factory()->create());

    $this->withHeader('User-Agent', iphoneUa())
        ->get(route('dashboard'))
        ->assertRedirect(route('mobile.home'));
});
