<?php

use App\Enum\AnnouncementType;
use App\Filament\Resources\Announcements\AnnouncementResource;
use App\Filament\Resources\Announcements\Pages\CreateAnnouncement;
use App\Filament\Resources\Announcements\Pages\ListAnnouncements;
use App\Models\Announcement;
use App\Models\Department;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Filament::setCurrentPanel('admin');
});

function announcementManager(string $role): User
{
    Role::findOrCreate($role);
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

it('allows manager roles to access the announcement resource', function (string $role) {
    $this->actingAs(announcementManager($role));

    expect(AnnouncementResource::canAccess())->toBeTrue();
})->with(['superadmin', 'super_admin', 'hr']);

it('denies regular users access to the announcement resource', function () {
    $this->actingAs(User::factory()->create());

    expect(AnnouncementResource::canAccess())->toBeFalse();
});

it('renders the announcement list for a manager', function () {
    $this->actingAs(announcementManager('hr'));
    Announcement::factory()->count(2)->create();

    Livewire::test(ListAnnouncements::class)->assertSuccessful();
});

it('badges only currently-live announcements', function () {
    $this->actingAs(announcementManager('hr'));

    expect(AnnouncementResource::getNavigationBadge())->toBeNull();

    Announcement::factory()->create(); // active, open-ended → live
    Announcement::factory()->inactive()->create(); // not live
    Announcement::factory()->create(['ends_at' => now()->subDay()]); // expired

    expect(AnnouncementResource::getNavigationBadge())->toBe('1');
});

it('creates an announcement with type, schedule and targeted departments', function () {
    $this->actingAs(announcementManager('hr'));
    $department = Department::factory()->create();

    Livewire::test(CreateAnnouncement::class)
        ->fillForm([
            'title' => 'System maintenance',
            'type' => AnnouncementType::WARNING->value,
            'is_active' => true,
            'starts_at' => now()->toDateTimeString(),
            'ends_at' => now()->addWeek()->toDateTimeString(),
            'departments' => [$department->id],
            'message' => '<p>The system will be down Saturday.</p>',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $announcement = Announcement::where('title', 'System maintenance')->firstOrFail();

    expect($announcement->type)->toBe(AnnouncementType::WARNING)
        ->and($announcement->is_active)->toBeTrue()
        ->and($announcement->departments->pluck('id')->all())->toBe([$department->id]);
});
