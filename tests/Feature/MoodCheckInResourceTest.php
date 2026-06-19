<?php

use App\Enum\Mood;
use App\Filament\Resources\MoodCheckIns\MoodCheckInResource;
use App\Filament\Resources\MoodCheckIns\Pages\ListMoodCheckIns;
use App\Filament\Widgets\Hr\MoodTodayWidget;
use App\Models\MoodCheckIn;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Filament::setCurrentPanel('admin');
});

function moodManager(string $role): User
{
    Role::findOrCreate($role);
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

it('allows manager roles to access the mood check-in resource', function (string $role) {
    $this->actingAs(moodManager($role));

    expect(MoodCheckInResource::canAccess())->toBeTrue();
})->with(['superadmin', 'super_admin', 'hr']);

it('denies regular users access to the mood check-in resource', function () {
    $this->actingAs(User::factory()->create());

    expect(MoodCheckInResource::canAccess())->toBeFalse();
});

it('never allows creating mood check-ins from the panel', function () {
    expect(MoodCheckInResource::canCreate())->toBeFalse();
});

it('renders the mood check-in list for a manager', function () {
    $this->actingAs(moodManager('hr'));
    MoodCheckIn::factory()->count(3)->create();

    Livewire::test(ListMoodCheckIns::class)->assertSuccessful();
});

it('renders the team mood widget for a manager', function () {
    $this->actingAs(moodManager('hr'));
    MoodCheckIn::factory()->mood(Mood::HAPPY)->create();
    MoodCheckIn::factory()->mood(Mood::STRESSED)->create();

    Livewire::test(MoodTodayWidget::class)->assertSuccessful();
});
