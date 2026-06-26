<x-filament-panels::page>
    {{-- Celebration greeting (the signed-in employee's own birthday / anniversary) --}}
    @php($celebration = $this->celebration())
    @if ($celebration)
        <div class="flex items-center gap-3 rounded-xl bg-gradient-to-r from-amber-100 to-pink-100 px-4 py-3 text-amber-900 ring-1 ring-amber-600/20 dark:from-amber-500/15 dark:to-pink-500/15 dark:text-amber-100 dark:ring-amber-400/30">
            <span class="text-2xl">{{ $celebration['emoji'] }}</span>
            <p class="text-sm font-semibold">{{ $celebration['message'] }}</p>
        </div>
    @endif

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
        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $this->todayLabel() }}
            </p>
    </div>

    {{-- Two-column widget grid --}}
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3 lg:items-start">
        
        {{-- Left column (2/3) --}}
        <div class="space-y-6 lg:col-span-2">
            @livewire(\App\Filament\Widgets\ClockInOutWidget::class)
            @livewire(\App\Filament\Widgets\Employee\EmployeeStatsWidget::class)
             {{-- Quick actions --}}
            <x-filament::section>
                <x-slot name="heading">Quick Links</x-slot>
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
            </x-filament::section>
            @livewire(\App\Filament\Widgets\Employee\MyRequestsWidget::class)
            
            @livewire(\App\Filament\Widgets\OnLeaveTodayWidget::class)
        </div>

        {{-- Right column (1/3) --}}
        <div class="space-y-6">
            @livewire(\App\Filament\Widgets\Employee\ComingUpWidget::class)
            @livewire(\App\Filament\Widgets\Employee\MyPraiseWidget::class)

            @livewire(\App\Filament\Widgets\Employee\MyTeamTodayWidget::class)
            @livewire(\App\Filament\Widgets\Employee\MyEquipmentWidget::class)
        </div>
    </div>

    {{-- Mood check-in: floating bubble + auto-opening modal (renders as a fixed overlay) --}}
    @livewire(\App\Filament\Widgets\MoodCheckInWidget::class)
</x-filament-panels::page>
