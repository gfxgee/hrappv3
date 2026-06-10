<x-filament-panels::page>
    {{-- Greeting header --}}
    <div class="flex flex-wrap items-end justify-between gap-2">
        <div>
            <h2 class="text-xl font-bold tracking-tight text-gray-950 dark:text-white">
                {{ $this->greeting() }}
            </h2>
            @if ($this->departmentName() || $this->leaderNames() !== [])
                <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                    {{ $this->departmentName() }}
                    @if ($this->leaderNames() !== [])
                        <span class="text-gray-400">·</span> reports to {{ implode(', ', $this->leaderNames()) }}
                    @endif
                </p>
            @endif
        </div>
        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $this->todayLabel() }}</p>
    </div>

    {{-- Personal stat cards --}}
    @livewire(\App\Filament\Widgets\Employee\EmployeeStatsWidget::class)

    {{-- Quick actions --}}
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
        @foreach ($this->quickActions() as $action)
            <a
                href="{{ $action['url'] }}"
                class="flex flex-col items-center gap-1.5 rounded-xl bg-white p-4 text-center shadow-sm ring-1 ring-gray-950/5 transition hover:bg-gray-50 dark:bg-white/5 dark:ring-white/10 dark:hover:bg-white/10"
            >
                <span class="text-xl">{{ $action['emoji'] }}</span>
                <span class="text-sm font-medium text-gray-950 dark:text-white">{{ $action['label'] }}</span>
            </a>
        @endforeach
    </div>

    {{-- Two-column widget grid --}}
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3 lg:items-start">
        {{-- Left column (2/3) --}}
        <div class="space-y-6 lg:col-span-2">
            @livewire(\App\Filament\Widgets\ClockInOutWidget::class)
            @livewire(\App\Filament\Widgets\Employee\MyRequestsWidget::class)
            @livewire(\App\Filament\Widgets\Employee\MyTeamTodayWidget::class)
            @livewire(\App\Filament\Widgets\OnLeaveTodayWidget::class)
        </div>

        {{-- Right column (1/3) --}}
        <div class="space-y-6">
            @livewire(\App\Filament\Widgets\Employee\MyPraiseWidget::class)
            @livewire(\App\Filament\Widgets\Employee\ComingUpWidget::class, ['windowDays' => 14])
        </div>
    </div>
</x-filament-panels::page>
