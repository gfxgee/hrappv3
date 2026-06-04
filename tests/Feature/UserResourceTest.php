<?php

use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\RelationManagers\AttendanceLogsRelationManager;
use App\Filament\Resources\Users\UserResource;
use App\Models\AttendanceLog;
use App\Models\LeaveRequest;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
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
