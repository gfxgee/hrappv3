<?php

use App\Enum\AttendanceStatus;
use App\Filament\Resources\Departments\DepartmentResource;
use App\Filament\Resources\Departments\Pages\CreateDepartment;
use App\Filament\Resources\Departments\Pages\ListDepartments;
use App\Filament\Resources\Departments\RelationManagers\LeadersRelationManager;
use App\Filament\Resources\LeaveRequests\Pages\ListLeaveRequests;
use App\Filament\Resources\OverTimeRequests\Pages\ListOverTimeRequests;
use App\Models\Department;
use App\Models\LeaveRequest;
use App\Models\OverTimeRequest;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Filament::setCurrentPanel('admin');
});

function departmentUser(?string $role = null): User
{
    $user = User::factory()->create();

    if ($role !== null) {
        Role::findOrCreate($role);
        $user->assignRole($role);
    }

    return $user;
}

it('relates a department to its users and leaders', function () {
    $department = Department::factory()->create();
    $member = User::factory()->create(['department_id' => $department->id]);
    $leader = User::factory()->create();
    $department->leaders()->attach($leader);

    expect($department->users->pluck('id'))->toContain($member->id)
        ->and($department->leaders->pluck('id'))->toContain($leader->id)
        ->and($member->department->is($department))->toBeTrue()
        ->and($leader->ledDepartments->pluck('id'))->toContain($department->id)
        ->and($leader->isTeamLeader())->toBeTrue();
});

it('allows hr and admins to access the department resource', function (string $role) {
    $this->actingAs(departmentUser($role));

    expect(DepartmentResource::canAccess())->toBeTrue();
})->with(['superadmin', 'super_admin', 'hr']);

it('denies team leaders and regular users access to the department resource', function () {
    $this->actingAs(departmentUser('teamleader'));
    expect(DepartmentResource::canAccess())->toBeFalse();

    $this->actingAs(departmentUser());
    expect(DepartmentResource::canAccess())->toBeFalse();
});

it('renders and creates departments', function () {
    $this->actingAs(departmentUser('hr'));

    Livewire::test(ListDepartments::class)->assertSuccessful();

    Livewire::test(CreateDepartment::class)
        ->fillForm(['name' => 'Engineering'])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Department::where('name', 'Engineering')->exists())->toBeTrue();
});

it('renders the leaders relation manager', function () {
    $this->actingAs(departmentUser('hr'));
    $department = Department::factory()->create();
    $department->leaders()->attach(User::factory()->create());

    Livewire::test(LeadersRelationManager::class, [
        'ownerRecord' => $department,
        'pageClass' => \App\Filament\Resources\Departments\Pages\EditDepartment::class,
    ])->assertSuccessful();
});

it('attaches an employee as a team leader via the relation manager', function () {
    $this->actingAs(departmentUser('hr'));
    $department = Department::factory()->create();
    $employee = User::factory()->create();

    Livewire::test(LeadersRelationManager::class, [
        'ownerRecord' => $department,
        'pageClass' => \App\Filament\Resources\Departments\Pages\EditDepartment::class,
    ])
        ->callTableAction('attach', data: ['recordId' => $employee->id])
        ->assertHasNoTableActionErrors();

    expect($department->leaders()->whereKey($employee->id)->exists())->toBeTrue();
});

it('limits the team-leader dropdown to active users via the active scope', function () {
    $active = User::factory()->create(['status' => App\Enum\UserStatus::ACTIVE->value]);
    $inactive = User::factory()->create(['status' => App\Enum\UserStatus::INACTIVE->value]);

    // The attach dropdown is populated with User::active() (see LeadersRelationManager).
    $ids = User::active()->pluck('id');

    expect($ids)->toContain($active->id)
        ->and($ids)->not->toContain($inactive->id);
});

it('shows a team leader only their own department\'s leave requests', function () {
    $deptA = Department::factory()->create();
    $deptB = Department::factory()->create();

    $memberA = User::factory()->create(['department_id' => $deptA->id]);
    $memberB = User::factory()->create(['department_id' => $deptB->id]);

    $leader = departmentUser('teamleader');
    $deptA->leaders()->attach($leader);

    $leaveA = LeaveRequest::factory()->for($memberA)->create();
    $leaveB = LeaveRequest::factory()->for($memberB)->create();

    $this->actingAs($leader);

    Livewire::test(ListLeaveRequests::class)
        ->assertCanSeeTableRecords([$leaveA])
        ->assertCanNotSeeTableRecords([$leaveB]);
});

it('shows hr all leave requests regardless of department', function () {
    $deptA = Department::factory()->create();
    $deptB = Department::factory()->create();

    $leaveA = LeaveRequest::factory()->for(User::factory()->create(['department_id' => $deptA->id]))->create();
    $leaveB = LeaveRequest::factory()->for(User::factory()->create(['department_id' => $deptB->id]))->create();

    $this->actingAs(departmentUser('hr'));

    Livewire::test(ListLeaveRequests::class)
        ->assertCanSeeTableRecords([$leaveA, $leaveB]);
});

it('shows a team leader only their own department\'s overtime requests', function () {
    $deptA = Department::factory()->create();
    $deptB = Department::factory()->create();

    $memberA = User::factory()->create(['department_id' => $deptA->id]);
    $memberB = User::factory()->create(['department_id' => $deptB->id]);

    $leader = departmentUser('teamleader');
    $deptA->leaders()->attach($leader);

    $otA = OverTimeRequest::factory()->for($memberA)->create(['status' => AttendanceStatus::FOR_APPROVAL]);
    $otB = OverTimeRequest::factory()->for($memberB)->create(['status' => AttendanceStatus::FOR_APPROVAL]);

    $this->actingAs($leader);

    Livewire::test(ListOverTimeRequests::class)
        ->assertCanSeeTableRecords([$otA])
        ->assertCanNotSeeTableRecords([$otB]);
});
