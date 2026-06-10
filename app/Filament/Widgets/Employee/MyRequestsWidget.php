<?php

namespace App\Filament\Widgets\Employee;

use App\Enum\AttendanceStatus;
use App\Enum\LeaveType;
use App\Models\LeaveRequest;
use App\Models\OverTimeRequest;
use Carbon\CarbonInterface;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;

/**
 * The user's latest leave and overtime requests, merged and sorted by
 * filing date, with status badges.
 */
class MyRequestsWidget extends Widget
{
    protected string $view = 'filament.widgets.employee.my-requests-widget';

    protected int|string|array $columnSpan = ['default' => 1, 'md' => 2];

    protected static ?int $sort = -2;

    /** How many merged requests to show. */
    public const LIMIT = 5;

    public static function canView(): bool
    {
        return auth()->check();
    }

    /**
     * Latest leave + overtime requests, merged, newest filing first.
     *
     * @return Collection<int, array{label: string, dates: string, status: AttendanceStatus, submitted: CarbonInterface}>
     */
    public function requests(): Collection
    {
        $leaves = LeaveRequest::query()
            ->where('user_id', auth()->id())
            ->latest()
            ->take(self::LIMIT)
            ->get()
            ->map(fn (LeaveRequest $leave): array => [
                'label' => $this->leaveTypeLabel($leave->request_type),
                'dates' => $this->dateRange($leave->start_date, $leave->end_date),
                'status' => $leave->status,
                'submitted' => $leave->created_at,
            ]);

        $overtimes = OverTimeRequest::query()
            ->where('user_id', auth()->id())
            ->latest()
            ->take(self::LIMIT)
            ->get()
            ->map(fn (OverTimeRequest $request): array => [
                'label' => '⏱️ Overtime · '.rtrim(rtrim(number_format((float) $request->hours, 1), '0'), '.').' h',
                'dates' => $request->request_date?->format('j M') ?? '—',
                'status' => $request->status,
                'submitted' => $request->created_at,
            ]);

        return $leaves->concat($overtimes)
            ->sortByDesc('submitted')
            ->take(self::LIMIT)
            ->values();
    }

    /**
     * Leave-type label tolerant of legacy rows with an empty type, whose
     * enum case has no match arm in label().
     */
    protected function leaveTypeLabel(?LeaveType $type): string
    {
        if ($type === null || $type === LeaveType::EMPTY) {
            return '🗓️ Leave';
        }

        return $type->label();
    }

    protected function dateRange(?CarbonInterface $start, ?CarbonInterface $end): string
    {
        if ($start === null) {
            return '—';
        }

        if ($end === null || $start->isSameDay($end)) {
            return $start->format('j M');
        }

        return $start->format('j').($start->isSameMonth($end) ? '' : ' '.$start->format('M')).'–'.$end->format('j M');
    }
}
