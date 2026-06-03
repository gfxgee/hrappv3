<?php

use App\Enum\AttendanceStatus;
use App\Enum\LeaveType;
use App\Filament\Widgets\OnLeaveTodayWidget;
use App\Models\LeaveRequest;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel('admin');
    $this->actingAs(User::factory()->create());
});

it('renders the widget', function () {
    Livewire::test(OnLeaveTodayWidget::class)->assertSuccessful();
});

it('shows leave requests that cover today', function () {
    $onLeave = LeaveRequest::factory()->create([
        'request_type' => LeaveType::VACATION,
        'status' => AttendanceStatus::APPROVED,
        'start_date' => today()->subDay(),
        'end_date' => today()->addDay(),
    ]);

    Livewire::test(OnLeaveTodayWidget::class)
        ->assertCanSeeTableRecords([$onLeave]);
});

it('hides past and future leave requests', function () {
    $past = LeaveRequest::factory()->create([
        'start_date' => today()->subDays(10),
        'end_date' => today()->subDays(5),
        'status' => AttendanceStatus::APPROVED,
    ]);
    $future = LeaveRequest::factory()->create([
        'start_date' => today()->addDays(5),
        'end_date' => today()->addDays(10),
        'status' => AttendanceStatus::APPROVED,
    ]);

    Livewire::test(OnLeaveTodayWidget::class)
        ->assertCanNotSeeTableRecords([$past, $future]);
});

it('hides cancelled and rejected leaves even if they cover today', function () {
    $cancelled = LeaveRequest::factory()->create([
        'start_date' => today()->subDay(),
        'end_date' => today()->addDay(),
        'status' => AttendanceStatus::CANCELLED,
    ]);
    $rejected = LeaveRequest::factory()->create([
        'start_date' => today()->subDay(),
        'end_date' => today()->addDay(),
        'status' => AttendanceStatus::REJECTED,
    ]);

    Livewire::test(OnLeaveTodayWidget::class)
        ->assertCanNotSeeTableRecords([$cancelled, $rejected]);
});
