<x-filament-panels::page>
    {{-- Org-wide stat cards --}}
    @livewire(\App\Filament\Widgets\Hr\HrStatsWidget::class)

    {{-- Charts row --}}
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3 lg:items-start">
        <div class="lg:col-span-2">
            @livewire(\App\Filament\Widgets\Hr\LeaveRequestsChartWidget::class)
        </div>
        <div>
            @livewire(\App\Filament\Widgets\Hr\OvertimeByDepartmentChartWidget::class)
        </div>
    </div>

    {{-- Stale approvals --}}
    @livewire(\App\Filament\Widgets\Hr\StalePendingApprovalsWidget::class)

    {{-- Bottom grid --}}
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3 lg:items-start">
        <div class="space-y-6 lg:col-span-2">
            @livewire(\App\Filament\Widgets\OnLeaveTodayWidget::class)
        </div>

        <div class="space-y-6">
            @livewire(\App\Filament\Widgets\Employee\ComingUpWidget::class, ['windowDays' => 7])
            @livewire(\App\Filament\Widgets\Hr\PraiseLeaderboardWidget::class)
            @livewire(\App\Filament\Widgets\Hr\HeadcountByDepartmentChartWidget::class)
        </div>
    </div>
</x-filament-panels::page>
