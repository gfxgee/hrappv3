<?php

use App\Models\User;
use Spatie\Permission\Models\Role;

function userWithImpersonationRole(string $role): User
{
    Role::findOrCreate($role);

    $user = User::factory()->create(['status' => 'active']);
    $user->assignRole($role);

    return $user;
}

it('lets a super admin impersonate a regular employee', function () {
    $admin = userWithImpersonationRole('superadmin');
    $employee = User::factory()->create(['status' => 'active']);

    $this->actingAs($admin)
        ->get(route('impersonate', $employee))
        ->assertRedirect('/admin');

    expect(session('impersonated_by'))->toBe($admin->id)
        // The password hash must be realigned to the impersonated user, or
        // Filament's AuthenticateSession middleware would log them straight out.
        ->and(session('password_hash_web'))->toBe($employee->getAuthPassword());
});

it('lets HR impersonate a regular employee', function () {
    $hr = userWithImpersonationRole('hr');
    $employee = User::factory()->create(['status' => 'active']);

    $this->actingAs($hr)
        ->get(route('impersonate', $employee))
        ->assertRedirect('/admin');

    expect(session('impersonated_by'))->toBe($hr->id);
});

it('restores the original user when leaving impersonation', function () {
    $admin = userWithImpersonationRole('superadmin');
    $employee = User::factory()->create(['status' => 'active']);

    $this->actingAs($admin)->get(route('impersonate', $employee));
    expect(session('impersonated_by'))->toBe($admin->id);

    $this->get(route('impersonate.leave'))->assertRedirect('/admin');

    expect(session()->has('impersonated_by'))->toBeFalse()
        ->and(session('password_hash_web'))->toBe($admin->getAuthPassword());
});

it('forbids a regular employee from impersonating anyone', function () {
    $employee = User::factory()->create(['status' => 'active']);
    $target = User::factory()->create(['status' => 'active']);

    $this->actingAs($employee)
        ->get(route('impersonate', $target))
        ->assertForbidden();

    expect(session()->has('impersonated_by'))->toBeFalse();
});

it('does not let HR impersonate a super admin', function () {
    $hr = userWithImpersonationRole('hr');
    $admin = userWithImpersonationRole('superadmin');

    $this->actingAs($hr)->get(route('impersonate', $admin));

    expect(session()->has('impersonated_by'))->toBeFalse();
});

it('does not let HR impersonate another manager', function () {
    $hr = userWithImpersonationRole('hr');
    $otherHr = userWithImpersonationRole('hr');

    $this->actingAs($hr)->get(route('impersonate', $otherHr));

    expect(session()->has('impersonated_by'))->toBeFalse();
});

it('lets a super admin impersonate an HR (only super admins can be blocked)', function () {
    $admin = userWithImpersonationRole('superadmin');
    $hr = userWithImpersonationRole('hr');

    $this->actingAs($admin)
        ->get(route('impersonate', $hr))
        ->assertRedirect('/admin');

    expect(session('impersonated_by'))->toBe($admin->id);
});

it('cannot impersonate yourself', function () {
    $admin = userWithImpersonationRole('superadmin');

    $this->actingAs($admin)
        ->get(route('impersonate', $admin))
        ->assertForbidden();
});
