<?php

use App\Models\User;
use Spatie\Permission\Models\Role;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('managers can visit the desktop dashboard', function () {
    Role::findOrCreate('hr', 'web');
    $user = User::factory()->create();
    $user->assignRole('hr');
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

test('regular employees are redirected from the dashboard to the mobile app', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('dashboard'))->assertRedirect(route('mobile.home'));
});