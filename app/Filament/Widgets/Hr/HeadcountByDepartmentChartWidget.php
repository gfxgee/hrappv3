<?php

namespace App\Filament\Widgets\Hr;

use App\Models\Department;
use App\Models\User;
use Filament\Widgets\ChartWidget;

/**
 * Active employee count per department, as a horizontal bar chart.
 */
class HeadcountByDepartmentChartWidget extends ChartWidget
{
    protected ?string $heading = 'Headcount by department';

    protected int|string|array $columnSpan = ['default' => 1, 'md' => 1];

    protected static ?int $sort = 1;

    public static function canView(): bool
    {
        return (bool) auth()->user()?->isManager();
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
        $counts = User::query()
            ->active()
            ->selectRaw('department_id, COUNT(*) as total')
            ->groupBy('department_id')
            ->pluck('total', 'department_id');

        $names = Department::query()->pluck('name', 'id');

        $rows = $counts
            ->map(fn (int $total, $departmentId): array => [
                'label' => filled($departmentId) ? ($names[$departmentId] ?? 'No department') : 'No department',
                'total' => $total,
            ])
            ->sortByDesc('total')
            ->values();

        return [
            'datasets' => [
                [
                    'label' => 'Employees',
                    'data' => $rows->pluck('total')->all(),
                    'backgroundColor' => '#6366f1',
                ],
            ],
            'labels' => $rows->pluck('label')->all(),
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
