<?php

namespace App\Filament\Widgets\Hr;

use App\Enum\AttendanceStatus;
use App\Models\LeaveRequest;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

/**
 * Stacked weekly bars of leave requests filed over the last ~90 days,
 * split into approved / pending / rejected.
 */
class LeaveRequestsChartWidget extends ChartWidget
{
    protected ?string $heading = 'Leave requests, last 90 days';

    protected int|string|array $columnSpan = ['default' => 1, 'md' => 2];

    protected static ?int $sort = -3;

    /** Full weeks shown (plus the current partial week). */
    public const WEEKS = 12;

    public static function canView(): bool
    {
        return (bool) auth()->user()?->isManager();
    }

    protected function getType(): string
    {
        return 'bar';
    }

    /**
     * Weeks are bucketed in PHP so the query stays portable between the
     * MySQL runtime and the sqlite test database.
     *
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        $start = now()->startOfWeek()->subWeeks(self::WEEKS);

        /** @var array<string, array{approved: int, pending: int, rejected: int}> $buckets */
        $buckets = [];
        $labels = [];

        for ($week = 0; $week <= self::WEEKS; $week++) {
            $weekStart = $start->copy()->addWeeks($week);
            $buckets[$weekStart->toDateString()] = ['approved' => 0, 'pending' => 0, 'rejected' => 0];
            $labels[] = $weekStart->format('M j');
        }

        LeaveRequest::query()
            ->where('created_at', '>=', $start)
            ->get(['created_at', 'status'])
            ->each(function (LeaveRequest $request) use (&$buckets): void {
                $key = Carbon::parse($request->created_at)->startOfWeek()->toDateString();

                if (! isset($buckets[$key])) {
                    return;
                }

                match ($request->status) {
                    AttendanceStatus::APPROVED, AttendanceStatus::APPROVED_AND_VERIFIED => $buckets[$key]['approved']++,
                    AttendanceStatus::FOR_APPROVAL => $buckets[$key]['pending']++,
                    AttendanceStatus::REJECTED => $buckets[$key]['rejected']++,
                    default => null, // cancelled excluded
                };
            });

        return [
            'datasets' => [
                ['label' => 'Approved', 'data' => array_column($buckets, 'approved'), 'backgroundColor' => '#22c55e'],
                ['label' => 'Pending', 'data' => array_column($buckets, 'pending'), 'backgroundColor' => '#f59e0b'],
                ['label' => 'Rejected', 'data' => array_column($buckets, 'rejected'), 'backgroundColor' => '#ef4444'],
            ],
            'labels' => $labels,
        ];
    }

    protected function getOptions(): array
    {
        return [
            'scales' => [
                'x' => ['stacked' => true],
                'y' => ['stacked' => true, 'ticks' => ['precision' => 0]],
            ],
        ];
    }
}
