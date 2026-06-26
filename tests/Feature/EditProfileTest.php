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

it('lets an employee link any number of government ID documents', function () {
    $user = User::factory()->create(['status' => 'active']);
    $this->actingAs($user);

    Livewire::test(EditProfile::class)
        ->fillForm([
            'government_documents' => [
                ['label' => 'SSS', 'url' => 'https://drive.google.com/sss'],
                ['label' => 'NBI Clearance', 'url' => 'https://drive.google.com/nbi'],
            ],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $documents = $user->refresh()->government_documents;

    expect($documents)->toHaveCount(2)
        ->and($documents[0]['label'])->toBe('SSS')
        ->and($documents[0]['url'])->toBe('https://drive.google.com/sss')
        ->and($documents[1]['label'])->toBe('NBI Clearance');
});

it('rejects an invalid government ID document link', function () {
    $user = User::factory()->create(['status' => 'active']);
    $this->actingAs($user);

    Livewire::test(EditProfile::class)
        ->fillForm([
            'government_documents' => [
                ['label' => 'SSS', 'url' => 'not-a-url'],
            ],
        ])
        ->call('save')
        ->assertHasFormErrors();
});

it('lets an employee maintain their PC specifications', function () {
    $user = User::factory()->create(['status' => 'active']);
    $this->actingAs($user);

    Livewire::test(EditProfile::class)
        ->fillForm([
            'pc_specifications' => [
                ['component' => 'RAM', 'details' => 'Corsair Vengeance 8GB'],
                ['component' => 'Mouse', 'details' => 'Logitech MX Master 3'],
            ],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $specs = $user->refresh()->pc_specifications;

    expect($specs)->toHaveCount(2)
        ->and($specs[0]['component'])->toBe('RAM')
        ->and($specs[0]['details'])->toBe('Corsair Vengeance 8GB')
        ->and($specs[1]['component'])->toBe('Mouse');
});

it('requires both the component and its details for a PC specification', function () {
    $user = User::factory()->create(['status' => 'active']);
    $this->actingAs($user);

    Livewire::test(EditProfile::class)
        ->fillForm([
            'pc_specifications' => [
                ['component' => 'RAM', 'details' => ''],
            ],
        ])
        ->call('save')
        ->assertHasFormErrors();
});
