<?php

namespace App\Filament\Widgets;

use App\Enum\AttendanceStatus;
use App\Enum\LeaveType;
use App\Models\LeaveRequest;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;

class WorkFromHomeTodayWidget extends Widget
{
    protected string $view = 'filament.widgets.work-from-home-today-widget';

    protected int|string|array $columnSpan = ['default' => 1, 'md' => 1];

    protected static ?int $sort = -3;

    public static function canView(): bool
    {
        return auth()->check();
    }

    /**
     * Employees working from home today (WFH leave covering today).
     *
     * @return Collection<int, LeaveRequest>
     */
    public function entries(): Collection
    {
        $today = today();

        return LeaveRequest::query()
            ->with('user')
            ->where('request_type', LeaveType::WFH->value)
            ->whereIn('status', [
                AttendanceStatus::FOR_APPROVAL->value,
                AttendanceStatus::APPROVED->value,
                AttendanceStatus::APPROVED_AND_VERIFIED->value,
            ])
            ->whereDate('start_date', '<=', $today)
            ->whereDate('end_date', '>=', $today)
            ->get()
            ->sortBy(fn (LeaveRequest $leave): string => $leave->user?->name ?? '')
            ->values();
    }
}
