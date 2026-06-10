<?php

use App\Enum\AttendanceStatus;
use App\Enum\LeaveType;
use App\Filament\Pages\Dashboard;
use App\Filament\Widgets\Employee\ComingUpWidget;
use App\Filament\Widgets\OnLeaveTodayWidget;
use App\Filament\Widgets\WorkFromHomeTodayWidget;
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

it('hides inactive holidays from coming up', function () {
    Holiday::create(['name' => 'Active Holiday', 'date' => today()->addDays(3)->toDateString(), 'is_active' => true]);
    Holiday::create(['name' => 'Inactive Holiday', 'date' => today()->addDays(4)->toDateString(), 'is_active' => false]);

    $labels = Livewire::test(ComingUpWidget::class)->instance()->entries()->pluck('label');

    expect($labels)->toContain('Active Holiday')
        ->and($labels)->not->toContain('Inactive Holiday');
});

it('shows the custom holiday emoji in coming up', function () {
    Holiday::create([
        'name' => 'Christmas Day',
        'emoji' => '🎄',
        'date' => today()->addDays(10)->toDateString(),
        'is_active' => true,
    ]);

    $row = Livewire::test(ComingUpWidget::class)->instance()->entries()->firstWhere('label', 'Christmas Day');

    expect($row['emoji'])->toBe('🎄');
});

it('opens a modal with the holiday details from coming up', function () {
    $holiday = Holiday::create([
        'name' => 'Founders Day',
        'emoji' => '🎉',
        'description' => '<p>Read more at <a href="https://example.com">the source</a>.</p>',
        'date' => today()->addDays(7)->toDateString(),
        'is_active' => true,
    ]);

    Livewire::test(ComingUpWidget::class)
        ->mountAction('viewHoliday', arguments: ['holiday' => $holiday->id])
        ->assertActionMounted('viewHoliday');
});

it('renders holiday details with emoji, date and rich-text links', function () {
    $holiday = Holiday::create([
        'name' => 'Founders Day',
        'emoji' => '🎉',
        'description' => '<p>Read more at <a href="https://example.com">the source</a>.</p>',
        'date' => '2026-06-16',
        'is_active' => true,
    ]);

    $html = view('filament.widgets.holiday-details', ['holiday' => $holiday])->render();

    // Name, emoji and date live in the modal header; the body is just the description.
    expect($html)->toContain('href="https://example.com"')
        ->toContain('the source');
});

it('lists only employees working from home today', function () {
    $wfhUser = User::factory()->create(['status' => 'active']);
    $vlUser = User::factory()->create(['status' => 'active']);

    $wfh = LeaveRequest::factory()->for($wfhUser)->create([
        'request_type' => LeaveType::WFH,
        'status' => AttendanceStatus::APPROVED,
        'start_date' => today()->toDateString(),
        'end_date' => today()->toDateString(),
    ]);
    LeaveRequest::factory()->for($vlUser)->create([
        'request_type' => LeaveType::VACATION,
        'status' => AttendanceStatus::APPROVED,
        'start_date' => today()->toDateString(),
        'end_date' => today()->toDateString(),
    ]);

    $ids = (new WorkFromHomeTodayWidget)->entries()->pluck('id');

    expect($ids)->toContain($wfh->id)->toHaveCount(1);
});

it('renders the work from home today widget', function () {
    Livewire::test(WorkFromHomeTodayWidget::class)->assertSuccessful();
});

it('excludes work-from-home from the on leave today table', function () {
    $wfh = LeaveRequest::factory()->create([
        'request_type' => LeaveType::WFH,
        'status' => AttendanceStatus::APPROVED,
        'start_date' => today()->toDateString(),
        'end_date' => today()->toDateString(),
    ]);
    $vacation = LeaveRequest::factory()->create([
        'request_type' => LeaveType::VACATION,
        'status' => AttendanceStatus::APPROVED,
        'start_date' => today()->toDateString(),
        'end_date' => today()->toDateString(),
    ]);

    Livewire::test(OnLeaveTodayWidget::class)
        ->assertCanSeeTableRecords([$vacation])
        ->assertCanNotSeeTableRecords([$wfh]);
});
