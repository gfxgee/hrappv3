<?php

use App\Enum\AttendanceStatus;
use App\Enum\LeaveType;
use App\Filament\Pages\Dashboard;
use App\Filament\Widgets\UpcomingBirthdaysWidget;
use App\Filament\Widgets\UpcomingHolidaysWidget;
use App\Filament\Widgets\UpcomingLeavesWidget;
use App\Models\Holiday;
use App\Models\LeaveRequest;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel('admin');
    $this->actingAs(User::factory()->create(['status' => 'active']));
});

it('renders the two-column dashboard with its widgets', function () {
    Livewire::test(Dashboard::class)->assertSuccessful();
});

it('renders the upcoming birthdays widget', function () {
    Livewire::test(UpcomingBirthdaysWidget::class)->assertSuccessful();
});

it('lists upcoming birthdays within the window, soonest first', function () {
    User::factory()->create(['status' => 'active', 'name' => 'Birthday Today', 'birthday' => today()->format('Y-m-d')]);
    User::factory()->create(['status' => 'active', 'name' => 'Birthday Soon', 'birthday' => today()->addDays(3)->format('Y-m-d')]);
    User::factory()->create(['status' => 'active', 'name' => 'Birthday Far', 'birthday' => today()->addDays(200)->format('Y-m-d')]);

    $rows = (new UpcomingBirthdaysWidget)->birthdays();
    $names = $rows->pluck('user.name');

    expect($rows->first()['user']->name)->toBe('Birthday Today')
        ->and($rows->first()['isToday'])->toBeTrue()
        ->and($names->contains('Birthday Soon'))->toBeTrue()
        ->and($names->contains('Birthday Far'))->toBeFalse();
});

it('ignores employees without a birthday', function () {
    User::factory()->create(['status' => 'active', 'name' => 'No Birthday', 'birthday' => null]);

    $names = (new UpcomingBirthdaysWidget)->birthdays()->pluck('user.name');

    expect($names->contains('No Birthday'))->toBeFalse();
});

it('lists upcoming holidays within the window, soonest first', function () {
    Holiday::create(['name' => 'Soon Holiday', 'date' => today()->addDays(5)->toDateString()]);
    Holiday::create(['name' => 'Far Holiday', 'date' => today()->addDays(200)->toDateString()]);
    Holiday::create(['name' => 'Past Holiday', 'date' => today()->subDays(2)->toDateString()]);

    $names = (new UpcomingHolidaysWidget)->holidays()->pluck('name');

    expect($names->first())->toBe('Soon Holiday')
        ->and($names->contains('Far Holiday'))->toBeFalse()
        ->and($names->contains('Past Holiday'))->toBeFalse();
});

it('renders the upcoming holidays widget', function () {
    Livewire::test(UpcomingHolidaysWidget::class)->assertSuccessful();
});

it('lists all employees upcoming leaves, excluding past and same-day starts', function () {
    $me = auth()->user();
    $other = User::factory()->create(['status' => 'active']);

    $mine = LeaveRequest::factory()->for($me)->create([
        'request_type' => LeaveType::VACATION,
        'status' => AttendanceStatus::APPROVED,
        'start_date' => today()->addDays(2)->toDateString(),
        'end_date' => today()->addDays(4)->toDateString(),
    ]);
    $theirs = LeaveRequest::factory()->for($other)->create([
        'status' => AttendanceStatus::APPROVED,
        'start_date' => today()->addDays(3)->toDateString(),
        'end_date' => today()->addDays(5)->toDateString(),
    ]);
    $past = LeaveRequest::factory()->for($me)->create([
        'status' => AttendanceStatus::APPROVED,
        'start_date' => today()->subDays(10)->toDateString(),
        'end_date' => today()->subDays(8)->toDateString(),
    ]);

    $ids = (new UpcomingLeavesWidget)->leaves()->pluck('request.id');

    expect($ids)->toContain($mine->id)
        ->and($ids)->toContain($theirs->id)
        ->and($ids)->not->toContain($past->id);
});

it('renders the upcoming leaves widget', function () {
    Livewire::test(UpcomingLeavesWidget::class)->assertSuccessful();
});
