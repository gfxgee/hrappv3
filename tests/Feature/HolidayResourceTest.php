<?php

use App\Enum\HolidayDuration;
use App\Filament\Resources\Holidays\HolidayResource;
use App\Filament\Resources\Holidays\Pages\CreateHoliday;
use App\Filament\Resources\Holidays\Pages\ListHolidays;
use App\Models\Holiday;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Filament::setCurrentPanel('admin');
});

function holidayManager(string $role): User
{
    Role::findOrCreate($role);

    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

it('allows manager roles to access the holiday resource', function (string $role) {
    $this->actingAs(holidayManager($role));

    expect(HolidayResource::canAccess())->toBeTrue();
})->with(['superadmin', 'super_admin', 'hr']);

it('denies team leaders and regular users access to the holiday resource', function () {
    $this->actingAs(holidayManager('teamleader'));
    expect(HolidayResource::canAccess())->toBeFalse();

    $this->actingAs(User::factory()->create());
    expect(HolidayResource::canAccess())->toBeFalse();
});

it('renders the holiday list for a manager', function () {
    $this->actingAs(holidayManager('hr'));
    Holiday::factory()->count(3)->create();

    Livewire::test(ListHolidays::class)->assertSuccessful();
});

it('creates a holiday', function () {
    $this->actingAs(holidayManager('hr'));

    Livewire::test(CreateHoliday::class)
        ->fillForm([
            'name' => 'Independence Day',
            'date' => '2026-06-12',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $holiday = Holiday::where('name', 'Independence Day')->whereDate('date', '2026-06-12')->firstOrFail();

    expect($holiday->is_active)->toBeTrue()
        ->and($holiday->duration)->toBe(HolidayDuration::FULL_DAY);
});

it('creates a half-day, inactive holiday', function () {
    $this->actingAs(holidayManager('hr'));

    Livewire::test(CreateHoliday::class)
        ->fillForm([
            'name' => 'Half Day',
            'date' => '2026-12-24',
            'duration' => HolidayDuration::FIRST_HALF->value,
            'is_active' => false,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $holiday = Holiday::where('name', 'Half Day')->firstOrFail();

    expect($holiday->duration)->toBe(HolidayDuration::FIRST_HALF)
        ->and($holiday->is_active)->toBeFalse();
});
