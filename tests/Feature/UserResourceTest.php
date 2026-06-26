<?php

use App\Enum\AttendanceStatus;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\Pages\ViewUser;
use App\Filament\Resources\Users\RelationManagers\AssetAssignmentsRelationManager;
use App\Filament\Resources\Users\RelationManagers\AttendanceLogsRelationManager;
use App\Filament\Resources\Users\RelationManagers\OverTimeRequestsRelationManager;
use App\Filament\Resources\Users\UserResource;
use App\Models\Asset;
use App\Models\AttendanceLog;
use App\Models\Department;
use App\Models\LeaveRequest;
use App\Models\OverTimeRequest;
use App\Models\User;
use App\Services\AssetAssignmentService;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Filament::setCurrentPanel('admin');

    // Manager (has a role) — full create/edit access for the default tests.
    Role::findOrCreate('hr');
    $manager = User::factory()->create();
    $manager->assignRole('hr');
    $this->actingAs($manager);
});

it('renders the list page', function () {
    Livewire::test(ListUsers::class)->assertSuccessful();
});

it('lets active employees access the panel but blocks inactive ones', function () {
    $panel = Filament::getPanel('admin');
    $active = User::factory()->create(['status' => 'active']);
    $inactive = User::factory()->create(['status' => 'inactive']);

    expect($active->canAccessPanel($panel))->toBeTrue()
        ->and($inactive->canAccessPanel($panel))->toBeFalse();
});

it('soft-deletes a user and preserves their records', function () {
    $user = User::factory()->create();
    $leave = LeaveRequest::factory()->for($user)->create();

    $user->delete();

    expect(User::find($user->id))->toBeNull()                 // hidden from normal queries
        ->and(User::withTrashed()->find($user->id)?->trashed())->toBeTrue()
        ->and(LeaveRequest::find($leave->id))->not->toBeNull(); // history survives
});

it('blocks force-deleting (hard delete) a user', function () {
    expect(UserResource::canForceDelete(User::factory()->create()))->toBeFalse();
});

it('shows the total user count as a navigation badge', function () {
    User::factory()->count(3)->create();

    expect(UserResource::getNavigationBadge())->toBe((string) User::count());
});

it('gives a user with no roles view-only access', function () {
    $viewer = User::factory()->create();
    $this->actingAs($viewer);

    expect(UserResource::canViewAny())->toBeTrue()
        ->and(UserResource::canView($viewer))->toBeTrue()
        ->and(UserResource::canCreate())->toBeFalse()
        ->and(UserResource::canEdit($viewer))->toBeFalse()
        ->and(UserResource::canDelete($viewer))->toBeFalse()
        ->and(UserResource::canDeleteAny())->toBeFalse();
});

it('gives a user with a role full management access', function () {
    // The beforeEach manager already has the hr role.
    expect(UserResource::canCreate())->toBeTrue()
        ->and(UserResource::canEdit(User::factory()->create()))->toBeTrue()
        ->and(UserResource::canDeleteAny())->toBeTrue();
});

it('shows view-only users the view action but hides edit', function () {
    $viewer = User::factory()->create();
    $this->actingAs($viewer);
    $record = User::factory()->create();

    Livewire::test(ListUsers::class)
        ->assertSuccessful()
        ->assertTableActionVisible('view', $record)
        ->assertTableActionHidden('edit', $record);
});

it('renders the create page', function () {
    Livewire::test(CreateUser::class)->assertSuccessful();
});

it('renders the edit page', function () {
    $user = User::factory()->create();

    Livewire::test(EditUser::class, ['record' => $user->getRouteKey()])
        ->assertSuccessful();
});

it('renders the read-only view page that names link to', function () {
    $user = User::factory()->create(['name' => 'Linked Employee']);

    Livewire::test(ViewUser::class, ['record' => $user->getRouteKey()])
        ->assertSuccessful()
        ->assertSee('Linked Employee');
});

it('creates a user along with the related user data', function () {
    Livewire::test(CreateUser::class)
        ->fillForm([
            'name' => 'Jane Tester',
            'email' => 'jane.tester@example.com',
            'password' => 'password123',
            'status' => 'active',
            'userData' => [
                'vacation_leave' => 10,
                'sick_leave' => 5,
                'time_in' => '10:00',
                'time_out' => '18:00',
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $user = User::where('email', 'jane.tester@example.com')->firstOrFail();

    expect($user->userData)->not->toBeNull();
    expect((int) $user->userData->vacation_leave)->toBe(10);
    expect($user->userData->time_in)->toBe('10:00');
});

it('uploads a photo to the public avatar directory', function () {
    Storage::fake('public');

    Livewire::test(CreateUser::class)
        ->fillForm([
            'name' => 'Photo User',
            'email' => 'photo.user@example.com',
            'password' => 'password123',
            'status' => 'active',
            'photo' => UploadedFile::fake()->image('me.jpg'),
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $user = User::where('email', 'photo.user@example.com')->firstOrFail();

    expect($user->photo)->toStartWith('avatar/');
    Storage::disk('public')->assertExists($user->photo);
});

it('resolves the filament avatar url from the photo column', function () {
    $withPhoto = User::factory()->create(['photo' => 'avatar/gee.jpg']);
    $withoutPhoto = User::factory()->create(['photo' => null]);

    expect($withPhoto->getFilamentAvatarUrl())->toBe(Storage::disk('public')->url('avatar/gee.jpg'));
    expect($withoutPhoto->getFilamentAvatarUrl())->toBeNull();
});

it('lets a super-admin assign roles to an employee through the form', function () {
    Role::findOrCreate('super_admin');
    Role::findOrCreate('teamleader');

    $admin = User::factory()->create();
    $admin->assignRole('super_admin');
    $this->actingAs($admin);

    $employee = User::factory()->create();
    $role = Role::findByName('teamleader');

    Livewire::test(EditUser::class, ['record' => $employee->getRouteKey()])
        ->fillForm(['roles' => [$role->id]])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($employee->refresh()->hasRole('teamleader'))->toBeTrue();
});

it('makes an employee a team leader by assigning led departments from the form', function () {
    // The beforeEach manager holds the hr role, which may manage team leaders.
    $employee = User::factory()->create();
    $department = Department::factory()->create();

    expect($employee->isTeamLeader())->toBeFalse();

    Livewire::test(EditUser::class, ['record' => $employee->getRouteKey()])
        ->fillForm(['ledDepartments' => [$department->id]])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($employee->refresh()->isTeamLeader())->toBeTrue();
});

it('hides the roles section from non-super-admins', function () {
    // The beforeEach manager only holds the hr role.
    $employee = User::factory()->create();

    Livewire::test(EditUser::class, ['record' => $employee->getRouteKey()])
        ->assertFormFieldDoesNotExist('roles');
});

it('lets HR link an employee\'s government ID documents and logs the change', function () {
    $employee = User::factory()->create();

    Livewire::test(EditUser::class, ['record' => $employee->getRouteKey()])
        ->fillForm([
            'government_documents' => [
                ['label' => 'SSS', 'url' => 'https://drive.google.com/sss'],
                ['label' => 'PhilHealth', 'url' => 'https://drive.google.com/phic'],
            ],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $employee->refresh();

    $activity = Activity::query()
        ->where('subject_type', User::class)
        ->where('subject_id', $employee->id)
        ->where('event', 'updated')
        ->latest('id')
        ->first();

    expect($employee->government_documents)->toHaveCount(2)
        ->and($employee->government_documents[0]['label'])->toBe('SSS')
        ->and($activity)->not->toBeNull()
        ->and(data_get($activity->properties->toArray(), 'attributes.government_documents.0.url'))
        ->toBe('https://drive.google.com/sss');
});

it('lets HR record an employee\'s PC specifications and logs the change', function () {
    $employee = User::factory()->create();

    Livewire::test(EditUser::class, ['record' => $employee->getRouteKey()])
        ->fillForm([
            'pc_specifications' => [
                ['component' => 'Monitor', 'details' => 'Dell P2422H 24"'],
                ['component' => 'RAM', 'details' => 'Corsair Vengeance 8GB'],
            ],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $employee->refresh();

    $activity = Activity::query()
        ->where('subject_type', User::class)
        ->where('subject_id', $employee->id)
        ->where('event', 'updated')
        ->latest('id')
        ->first();

    expect($employee->pc_specifications)->toHaveCount(2)
        ->and($employee->pc_specifications[0]['component'])->toBe('Monitor')
        ->and($activity)->not->toBeNull()
        ->and(data_get($activity->properties->toArray(), 'attributes.pc_specifications.1.details'))
        ->toBe('Corsair Vengeance 8GB');
});

it('lists a user\'s assigned equipment in the relation manager', function () {
    $employee = User::factory()->create();
    $asset = Asset::factory()->create(['name' => 'Logitech MX Master 3']);
    app(AssetAssignmentService::class)->assign($asset, $employee);

    $assignment = $employee->assetAssignments()->firstOrFail();

    Livewire::test(AssetAssignmentsRelationManager::class, [
        'ownerRecord' => $employee,
        'pageClass' => EditUser::class,
    ])
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$assignment])
        ->assertSee('Logitech MX Master 3');
});

it('lists a user\'s attendance logs in the relation manager', function () {
    $user = User::factory()->create();
    $login = AttendanceLog::create([
        'user_id' => $user->id,
        'type' => 'clockin',
        'device' => 'web',
    ]);
    $logout = AttendanceLog::create([
        'user_id' => $user->id,
        'type' => 'clockout',
        'device' => 'web',
    ]);

    Livewire::test(AttendanceLogsRelationManager::class, [
        'ownerRecord' => $user,
        'pageClass' => EditUser::class,
    ])
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$login, $logout]);
});

it('filters attendance logs by a date range', function () {
    $user = User::factory()->create();

    $old = AttendanceLog::create(['user_id' => $user->id, 'type' => 'clockin', 'device' => 'web']);
    $old->forceFill(['created_at' => '2026-06-01 09:00:00'])->save();

    $recent = AttendanceLog::create(['user_id' => $user->id, 'type' => 'clockin', 'device' => 'web']);
    $recent->forceFill(['created_at' => '2026-06-15 09:00:00'])->save();

    Livewire::test(AttendanceLogsRelationManager::class, [
        'ownerRecord' => $user,
        'pageClass' => EditUser::class,
    ])
        ->filterTable('logged_between', ['from' => '2026-06-10', 'until' => '2026-06-20'])
        ->assertCanSeeTableRecords([$recent])
        ->assertCanNotSeeTableRecords([$old]);
});

it('lists overtime requests with a footer sum of hours', function () {
    $user = User::factory()->create();

    $first = OverTimeRequest::factory()->for($user)->create([
        'status' => AttendanceStatus::APPROVED,
        'request_date' => '2026-06-02 18:00:00',
        'hours' => 2.5,
    ]);
    $second = OverTimeRequest::factory()->for($user)->create([
        'status' => AttendanceStatus::FOR_APPROVAL,
        'request_date' => '2026-06-03 18:00:00',
        'hours' => 1.5,
    ]);
    $otherUsers = OverTimeRequest::factory()->create(['hours' => 9.0]);

    Livewire::test(OverTimeRequestsRelationManager::class, [
        'ownerRecord' => $user,
        'pageClass' => EditUser::class,
    ])
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$first, $second])
        ->assertCanNotSeeTableRecords([$otherUsers])
        ->assertSeeText('4.00 h'); // footer sum: 2.5 + 1.5
});

it('filters overtime requests by a date range', function () {
    $user = User::factory()->create();

    $inRange = OverTimeRequest::factory()->for($user)->create(['request_date' => '2026-06-15 18:00:00']);
    $outOfRange = OverTimeRequest::factory()->for($user)->create(['request_date' => '2026-05-01 18:00:00']);

    Livewire::test(OverTimeRequestsRelationManager::class, [
        'ownerRecord' => $user,
        'pageClass' => EditUser::class,
    ])
        ->filterTable('requested_between', ['from' => '2026-06-10', 'until' => '2026-06-20'])
        ->assertCanSeeTableRecords([$inRange])
        ->assertCanNotSeeTableRecords([$outOfRange]);
});

it('applies the this-month quick filter to overtime requests', function () {
    $user = User::factory()->create();

    $thisMonth = OverTimeRequest::factory()->for($user)->create([
        'request_date' => now()->startOfMonth()->addDays(3)->setHour(18),
    ]);
    $lastMonth = OverTimeRequest::factory()->for($user)->create([
        'request_date' => now()->subMonthNoOverflow()->startOfMonth()->addDays(3)->setHour(18),
    ]);

    Livewire::test(OverTimeRequestsRelationManager::class, [
        'ownerRecord' => $user,
        'pageClass' => EditUser::class,
    ])
        ->callTableAction('thisMonth')
        ->assertCanSeeTableRecords([$thisMonth])
        ->assertCanNotSeeTableRecords([$lastMonth])
        ->callTableAction('lastMonth')
        ->assertCanSeeTableRecords([$lastMonth])
        ->assertCanNotSeeTableRecords([$thisMonth]);
});

it('creates an overtime request from the relation manager', function () {
    $user = User::factory()->create();

    Livewire::test(OverTimeRequestsRelationManager::class, [
        'ownerRecord' => $user,
        'pageClass' => EditUser::class,
    ])
        ->callTableAction('create', data: [
            'request_date' => '2026-06-11',
            'hours' => 2.5,
            'reason' => 'Production deploy support',
            'status' => AttendanceStatus::FOR_APPROVAL->value,
        ])
        ->assertHasNoTableActionErrors();

    $request = OverTimeRequest::where('user_id', $user->id)->firstOrFail();

    expect((float) $request->hours)->toBe(2.5)
        ->and($request->status)->toBe(AttendanceStatus::FOR_APPROVAL);
});

it('exports overtime requests as a CSV download', function () {
    $user = User::factory()->create();
    OverTimeRequest::factory()->for($user)->create();

    Livewire::test(OverTimeRequestsRelationManager::class, [
        'ownerRecord' => $user,
        'pageClass' => EditUser::class,
    ])
        ->callTableAction('export')
        ->assertFileDownloaded();
});

it('exports attendance logs as a CSV download', function () {
    $user = User::factory()->create();
    AttendanceLog::create(['user_id' => $user->id, 'type' => 'clockin', 'device' => 'web']);

    Livewire::test(AttendanceLogsRelationManager::class, [
        'ownerRecord' => $user,
        'pageClass' => EditUser::class,
    ])
        ->callTableAction('export')
        ->assertFileDownloaded();
});
