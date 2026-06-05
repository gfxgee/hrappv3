<x-filament-panels::page>
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3 lg:items-start">
        {{-- Left column (2/3) --}}
        <div class="space-y-6 lg:col-span-2">
            @livewire(\App\Filament\Widgets\ClockInOutWidget::class)
            @livewire(\App\Filament\Widgets\OnLeaveTodayWidget::class)
        </div>

        {{-- Right column (1/3) --}}
        <div class="space-y-6">
            @livewire(\App\Filament\Widgets\WorkFromHomeTodayWidget::class)
            @livewire(\App\Filament\Widgets\UpcomingBirthdaysWidget::class)
            @livewire(\App\Filament\Widgets\UpcomingHolidaysWidget::class)
            @livewire(\App\Filament\Widgets\UpcomingLeavesWidget::class)
        </div>
    </div>
</x-filament-panels::page>
