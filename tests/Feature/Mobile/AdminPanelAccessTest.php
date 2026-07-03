<?php

use App\Models\User;
use Filament\Facades\Filament;

beforeEach(function () {
    Filament::setCurrentPanel('admin');
});

it('redirects a phone visitor from the admin panel to the mobile app', function () {
    $this->actingAs(User::factory()->create());

    $this->withHeader('User-Agent', iphoneUa())
        ->get('/admin')
        ->assertRedirect(route('mobile.home'));
});

it('lets a desktop visitor into the admin panel', function () {
    $this->actingAs(User::factory()->create());

    $this->get('/admin')->assertOk();
});
