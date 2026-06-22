<?php

use App\Enum\AttendanceCorrectionType;
use App\Enum\AttendanceStatus;
use App\Filament\Resources\AttendanceCorrectionRequests\AttendanceCorrectionRequestResource;
use App\Filament\Resources\AttendanceCorrectionRequests\Pages\ListAttendanceCorrectionRequests;
use App\Models\AttendanceCorrectionRequest;
use App\Models\AttendanceLog;
use App\Models\Department;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Notifications\DatabaseNotification;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Filament::setCurrentPanel('admin');
});

function correctionManager(string $role): User
{
    Role::findOrCreate($role);
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

it('allows manager roles to access the resource', function (string $role) {
    $this->actingAs(correctionManager($role));

    expect(AttendanceCorrectionRequestResource::canAccess())->toBeTrue();
})->with(['superadmin', 'super_admin', 'hr']);

it('denies regular users access to the resource', function () {
    $this->actingAs(User::factory()->create());

    expect(AttendanceCorrectionRequestResource::canAccess())->toBeFalse();
});

it('allows a department leader to access the resource without a manager role', function () {
    $leader = User::factory()->create();
    $leader->ledDepartments()->attach(Department::factory()->create());
    $this->actingAs($leader);

    expect(AttendanceCorrectionRequestResource::canAccess())->toBeTrue();
});

it('scopes a team leader to only their department\'s requests', function () {
    $department = Department::factory()->create();
    $leader = User::factory()->create();
    $leader->ledDepartments()->attach($department);

    $member = User::factory()->create(['department_id' => $department->id]);
    $outsider = User::factory()->create();
    $mine = AttendanceCorrectionRequest::factory()->for($member)->create();
    $theirs = AttendanceCorrectionRequest::factory()->for($outsider)->create();

    $this->actingAs($leader);

    Livewire::test(ListAttendanceCorrectionRequests::class)
        ->assertCanSeeTableRecords([$mine])
        ->assertCanNotSeeTableRecords([$theirs]);
});

it('notifies HR when a correction is filed', function () {
    $hr = correctionManager('hr');
    $employee = User::factory()->create();

    AttendanceCorrectionRequest::factory()->for($employee)->create(['status' => AttendanceStatus::FOR_APPROVAL]);

    expect(DatabaseNotification::query()->where('notifiable_id', $hr->id)->count())->toBe(1);
});

it('applies the correction to the attendance log when approved', function () {
    $this->actingAs(correctionManager('hr'));
    $employee = User::factory()->create();
    $request = AttendanceCorrectionRequest::factory()->for($employee)->create([
        'correction_type' => AttendanceCorrectionType::MISSING_CLOCK_OUT,
        'corrected_at' => now()->setTime(18, 0),
        'status' => AttendanceStatus::FOR_APPROVAL,
    ]);

    Livewire::test(ListAttendanceCorrectionRequests::class)
        ->callTableAction('approve', $request);

    expect($request->refresh()->status)->toBe(AttendanceStatus::APPROVED)
        ->and(AttendanceLog::query()->where('user_id', $employee->id)->where('type', 'clockout')->exists())->toBeTrue();
});

it('never allows creating requests from the panel', function () {
    expect(AttendanceCorrectionRequestResource::canCreate())->toBeFalse();
});

it('badges only requests awaiting a decision', function () {
    $this->actingAs(correctionManager('hr'));

    expect(AttendanceCorrectionRequestResource::getNavigationBadge())->toBeNull();

    AttendanceCorrectionRequest::factory()->count(2)->create();
    AttendanceCorrectionRequest::factory()->status(AttendanceStatus::APPROVED)->create();

    expect(AttendanceCorrectionRequestResource::getNavigationBadge())->toBe('2');
});

it('approves a request with remarks', function () {
    $this->actingAs(correctionManager('hr'));
    $request = AttendanceCorrectionRequest::factory()->create();

    Livewire::test(ListAttendanceCorrectionRequests::class)
        ->callTableAction('approve', $request, data: ['remarks' => 'Applied to your log.']);

    $request->refresh();

    expect($request->status)->toBe(AttendanceStatus::APPROVED)
        ->and($request->remarks)->toBe('Applied to your log.');
});

it('rejects a request and requires a reason', function () {
    $this->actingAs(correctionManager('hr'));
    $request = AttendanceCorrectionRequest::factory()->create();

    Livewire::test(ListAttendanceCorrectionRequests::class)
        ->callTableAction('reject', $request, data: ['remarks' => ''])
        ->assertHasTableActionErrors(['remarks' => 'required']);

    Livewire::test(ListAttendanceCorrectionRequests::class)
        ->callTableAction('reject', $request, data: ['remarks' => 'Punch already recorded.']);

    expect($request->refresh()->status)->toBe(AttendanceStatus::REJECTED);
});
