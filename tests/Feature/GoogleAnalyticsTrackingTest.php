<?php

use App\Models\Department;
use App\Models\User;
use Spatie\Permission\Models\Role;

it('sends a pseudonymous user_id and properties for an authenticated user', function () {
    Role::findOrCreate('hr');
    $department = Department::factory()->create(['name' => 'Engineering']);
    $user = User::factory()->create([
        'name' => 'Gee Actub',
        'email' => 'gee@example.com',
        'department_id' => $department->id,
    ]);
    $user->assignRole('hr');

    $this->actingAs($user);

    $html = view('components.google-analytics')->render();

    expect($html)->toContain('user_id')
        ->toContain("'".$user->id."'")   // numeric id sent
        ->toContain('Engineering')        // department property
        ->toContain('hr')                 // role property
        ->not->toContain('Gee Actub')     // never leak the name
        ->not->toContain('gee@example.com'); // never leak the email
});

it('does not send a user_id for guests', function () {
    $html = view('components.google-analytics')->render();

    expect($html)->not->toContain('user_id');
});
