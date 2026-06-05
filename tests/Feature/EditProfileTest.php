<?php

use App\Filament\Auth\EditProfile;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel('admin');
});

it('disables the name and email fields on the profile page', function () {
    $this->actingAs(User::factory()->create(['status' => 'active']));

    Livewire::test(EditProfile::class)
        ->assertSuccessful()
        ->assertFormFieldIsDisabled('name')
        ->assertFormFieldIsDisabled('email');
});

it('lets an employee change only their password', function () {
    $user = User::factory()->create([
        'status' => 'active',
        'name' => 'Original Name',
        'email' => 'original@example.com',
    ]);
    $this->actingAs($user);

    Livewire::test(EditProfile::class)
        ->fillForm([
            'name' => 'Hacked Name',
            'email' => 'hacked@example.com',
            'currentPassword' => 'password',
            'password' => 'new-password-123',
            'passwordConfirmation' => 'new-password-123',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $user->refresh();

    expect($user->name)->toBe('Original Name')
        ->and($user->email)->toBe('original@example.com')
        ->and(Hash::check('new-password-123', $user->password))->toBeTrue();
});
