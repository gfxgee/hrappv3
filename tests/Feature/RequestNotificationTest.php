<?php

use App\Enum\AttendanceStatus;
use App\Enum\LeaveType;
use App\Enum\UserStatus;
use App\Models\Department;
use App\Models\LeaveRequest;
use App\Models\OverTimeRequest;
use App\Models\User;
use Filament\Facades\Filament;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Filament::setCurrentPanel('admin');

    foreach (['superadmin', 'super_admin', 'hr', 'teamleader'] as $role) {
        Role::findOrCreate($role);
    }
});

function userWithRoleNamed(string $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

it('notifies the department team leader and HR when a leave request is filed', function () {
    $department = Department::factory()->create();

    $teamLeader = userWithRoleNamed('teamleader');
    $teamLeader->ledDepartments()->attach($department);

    $hr = userWithRoleNamed('hr');

    $employee = User::factory()->create(['department_id' => $department->id]);

    LeaveRequest::factory()->for($employee)->create([
        'request_type' => LeaveType::VACATION,
        'status' => AttendanceStatus::FOR_APPROVAL,
    ]);

    expect($teamLeader->notifications()->count())->toBe(1)
        ->and($hr->notifications()->count())->toBe(1)
        ->and($employee->notifications()->count())->toBe(0);
});

it('notifies approvers when an overtime request is filed', function () {
    $hr = userWithRoleNamed('hr');
    $employee = User::factory()->create();

    OverTimeRequest::factory()->for($employee)->create([
        'hours' => 3.5,
        'status' => AttendanceStatus::FOR_APPROVAL,
    ]);

    expect($hr->notifications()->count())->toBe(1);
});

it('falls back to super-admins when no team leader or HR exists', function () {
    $superAdmin = userWithRoleNamed('superadmin');
    $employee = User::factory()->create();

    LeaveRequest::factory()->for($employee)->create([
        'status' => AttendanceStatus::FOR_APPROVAL,
    ]);

    expect($superAdmin->notifications()->count())->toBe(1);
});

it('does not fall back to super-admins when HR is set', function () {
    $superAdmin = userWithRoleNamed('superadmin');
    $hr = userWithRoleNamed('hr');
    $employee = User::factory()->create();

    LeaveRequest::factory()->for($employee)->create([
        'status' => AttendanceStatus::FOR_APPROVAL,
    ]);

    expect($hr->notifications()->count())->toBe(1)
        ->and($superAdmin->notifications()->count())->toBe(0);
});

it('does not notify on a request that is not for approval', function () {
    $hr = userWithRoleNamed('hr');
    $employee = User::factory()->create();

    LeaveRequest::factory()->for($employee)->create([
        'status' => AttendanceStatus::APPROVED,
    ]);

    expect($hr->notifications()->count())->toBe(0);
});

it('never notifies the requester about their own filing', function () {
    // A lone HR member files their own leave: they are excluded, so it
    // falls back to the super-admin instead of notifying no one.
    $superAdmin = userWithRoleNamed('superadmin');
    $hr = userWithRoleNamed('hr');

    LeaveRequest::factory()->for($hr)->create([
        'status' => AttendanceStatus::FOR_APPROVAL,
    ]);

    expect($hr->notifications()->count())->toBe(0)
        ->and($superAdmin->notifications()->count())->toBe(1);
});

it('excludes inactive approvers from notifications', function () {
    $activeHr = userWithRoleNamed('hr');
    $inactiveHr = userWithRoleNamed('hr');
    $inactiveHr->update(['status' => UserStatus::INACTIVE->value]);

    $employee = User::factory()->create();

    LeaveRequest::factory()->for($employee)->create([
        'status' => AttendanceStatus::FOR_APPROVAL,
    ]);

    expect($activeHr->notifications()->count())->toBe(1)
        ->and($inactiveHr->notifications()->count())->toBe(0);
});
