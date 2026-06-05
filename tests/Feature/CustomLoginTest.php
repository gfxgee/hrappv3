<?php

use App\Filament\Auth\Login;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel('admin');
});

it('renders the custom split-screen login page for guests', function () {
    $this->get('/admin/login')
        ->assertSuccessful()
        ->assertSee('Welcome back')
        ->assertSee((new Login)->brandName());
});

it('uses the custom Login page class for the admin panel', function () {
    expect(Filament::getPanel('admin')->getLoginRouteAction())->toBe(Login::class);
});

it('authenticates a valid active user through the custom login page', function () {
    $user = User::factory()->create(['status' => 'active']);

    Livewire::test(Login::class)
        ->fillForm(['email' => $user->email, 'password' => 'password'])
        ->call('authenticate')
        ->assertHasNoFormErrors();

    $this->assertAuthenticated();
});

it('rejects invalid credentials', function () {
    $user = User::factory()->create();

    Livewire::test(Login::class)
        ->fillForm(['email' => $user->email, 'password' => 'wrong-password'])
        ->call('authenticate')
        ->assertHasFormErrors(['email']);

    $this->assertGuest();
});

it('returns an array of carousel images', function () {
    expect((new Login)->carouselImages())->toBeArray();
});

it('redirects the framework login route to the panel login', function () {
    $this->get('/login')->assertRedirect(route('filament.admin.auth.login'));
});
