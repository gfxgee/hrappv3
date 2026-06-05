<?php

use App\Models\User;
use Filament\Facades\Filament;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

beforeEach(function () {
    Filament::setCurrentPanel('admin');
});

function fakeMicrosoftUser(string $email): void
{
    Socialite::fake('microsoft', (new SocialiteUser)->map([
        'id' => 'ms-'.md5($email),
        'name' => 'SSO User',
        'email' => $email,
    ]));
}

it('redirects to Microsoft when starting SSO', function () {
    Socialite::fake('microsoft');

    $this->get(route('sso.microsoft.redirect'))->assertRedirect();
});

it('logs in an existing active employee via Microsoft SSO', function () {
    $user = User::factory()->create(['email' => 'employee@example.com', 'status' => 'active']);
    fakeMicrosoftUser('employee@example.com');

    $this->get(route('sso.microsoft.callback'))
        ->assertRedirect(Filament::getPanel('admin')->getUrl());

    $this->assertAuthenticatedAs($user);
});

it('denies SSO when no employee matches the email', function () {
    fakeMicrosoftUser('stranger@example.com');

    $this->get(route('sso.microsoft.callback'))
        ->assertRedirect(route('filament.admin.auth.login'));

    $this->assertGuest();
});

it('denies SSO for an inactive employee', function () {
    User::factory()->create(['email' => 'inactive@example.com', 'status' => 'inactive']);
    fakeMicrosoftUser('inactive@example.com');

    $this->get(route('sso.microsoft.callback'))
        ->assertRedirect(route('filament.admin.auth.login'));

    $this->assertGuest();
});
