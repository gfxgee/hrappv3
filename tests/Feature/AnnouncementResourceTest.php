<?php

use App\Enum\AnnouncementType;
use App\Filament\Resources\Announcements\AnnouncementResource;
use App\Filament\Resources\Announcements\Pages\CreateAnnouncement;
use App\Filament\Resources\Announcements\Pages\ListAnnouncements;
use App\Filament\Resources\Announcements\Tables\AnnouncementsTable;
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

it('trims the announcement message to a short excerpt for the table', function () {
    $long = '<p>'.str_repeat('word ', 200).'</p>';

    $excerpt = AnnouncementsTable::excerpt($long);

    expect(mb_strlen($excerpt))->toBeLessThanOrEqual(123) // 120 + "..."
        ->and($excerpt)->toEndWith('...');
});

it('decodes entities and keeps words apart in the excerpt', function () {
    $message = '<p>A &quot;Fit to Work&quot; note is required.</p><p>Vacation Leave</p><p>Employees may avail.</p>';

    $excerpt = AnnouncementsTable::excerpt($message, 400);

    // Real quotes, not "&quot;" — and block tags become spaces so the last word
    // of one paragraph doesn't glue onto the first word of the next.
    expect($excerpt)->toContain('"Fit to Work"')
        ->and($excerpt)->toContain('Vacation Leave Employees')
        ->and($excerpt)->not->toContain('&quot;')
        ->and($excerpt)->not->toContain('LeaveEmployees');
});

it('returns an empty excerpt for a blank message', function () {
    expect(AnnouncementsTable::excerpt(null))->toBe('');
});
