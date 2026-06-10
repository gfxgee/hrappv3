<?php

namespace App\Filament\Widgets\Hr;

use App\Enum\AttendanceStatus;
use App\Models\OverTimeRequest;
use Filament\Widgets\ChartWidget;

/**
 * Approved overtime hours per department for the current month.
 */
class OvertimeByDepartmentChartWidget extends ChartWidget
{
    protected int|string|array $columnSpan = ['default' => 1, 'md' => 1];

    protected static ?int $sort = -2;

    public static function canView(): bool
    {
        return (bool) auth()->user()?->isManager();
    }

    public function getHeading(): string
    {
        return 'Overtime by department, '.now()->format('M');
    }

    protected function getType(): string
    {
        return 'bar';
    }

    /**
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        $rows = OverTimeRequest::query()
            ->join('users', 'users.id', '=', 'over_time_requests.user_id')
            ->whereNull('users.deleted_at')
            ->leftJoin('departments', 'departments.id', '=', 'users.department_id')
            ->whereIn('over_time_requests.status', [AttendanceStatus::APPROVED->value, AttendanceStatus::APPROVED_AND_VERIFIED->value])
            ->whereBetween('over_time_requests.request_date', [now()->startOfMonth(), now()->endOfMonth()])
            ->groupBy('departments.id', 'departments.name')
            ->selectRaw("COALESCE(departments.name, 'No department') as department, SUM(over_time_requests.hours) as total_hours")
            ->orderByDesc('total_hours')
            ->get();

        return [
            'datasets' => [
                [
                    'label' => 'Hours',
                    'data' => $rows->pluck('total_hours')->map(fn ($hours): float => round((float) $hours, 1))->all(),
                    'backgroundColor' => '#8b5cf6',
                ],
            ],
            'labels' => $rows->pluck('department')->all(),
        ];
    }

    protected function getOptions(): array
    {
        return [
            'indexAxis' => 'y',
            'plugins' => ['legend' => ['display' => false]],
            'scales' => ['x' => ['ticks' => ['precision' => 0]]],
        ];
    }
}
