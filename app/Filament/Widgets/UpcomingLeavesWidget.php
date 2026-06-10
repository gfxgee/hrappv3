<?php

namespace App\Filament\Widgets;

use App\Enum\AttendanceStatus;
use App\Models\LeaveRequest;
use App\Settings\GeneralSettings;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;

class UpcomingLeavesWidget extends Widget
{
    protected string $view = 'filament.widgets.upcoming-leaves-widget';

    protected int|string|array $columnSpan = ['default' => 1, 'md' => 1];

    protected static ?int $sort = 1;

    /** Default look-ahead window (days), seeded into settings. */
    public const WINDOW_DAYS = 60;

    public static function canView(): bool
    {
        return auth()->check();
    }

    /** Configured look-ahead window in days. */
    public function windowDays(): int
    {
        return app(GeneralSettings::class)->leaveWindowDays;
    }

    /**
     * All employees' leaves starting within the upcoming window (active
     * statuses), soonest first.
     *
     * @return Collection<int, array{request: LeaveRequest, days: int}>
     */
    public function leaves(): Collection
    {
        $today = today();

        return LeaveRequest::query()
            ->with('user')
            ->whereIn('status', [
                AttendanceStatus::FOR_APPROVAL->value,
                AttendanceStatus::APPROVED->value,
                AttendanceStatus::APPROVED_AND_VERIFIED->value,
            ])
            ->whereDate('start_date', '>', $today)
            ->whereDate('start_date', '<=', $today->copy()->addDays($this->windowDays()))
            ->orderBy('start_date')
            ->take(8)
            ->get()
            ->map(fn (LeaveRequest $request): array => [
                'request' => $request,
                'days' => (int) $today->diffInDays($request->start_date),
            ]);
    }
}
