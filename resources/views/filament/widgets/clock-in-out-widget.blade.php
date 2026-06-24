@php
    $status = $this->getStatus();
    $clockIn = $this->getClockInLog();
    $clockOut = $this->getClockOutLog();
    $elapsed = $this->getElapsedHuman();

    $subtitle = match ($status) {
        'in_progress' => 'Shift in progress',
        'done' => 'Shift complete',
        default => now()->format('l, F j, Y'),
    };
@endphp

<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">Time Tracking</x-slot>
        <x-slot name="description">{{ $subtitle }}</x-slot>

        <div class="space-y-6">
            {{-- Stat cards --}}
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div class="rounded-xl border border-gray-200 p-4 dark:border-white/10">
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        Clock In
                    </p>
                    <p class="mt-1 text-xl font-bold text-gray-950 dark:text-white">
                        {{ $this->formatLogTime($clockIn) }}
                    </p>
                </div>

                <div class="rounded-xl border border-gray-200 p-4 dark:border-white/10">
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        Clock Out
                    </p>
                    <p class="mt-1 text-xl font-bold text-gray-950 dark:text-white">
                        {{ $this->formatLogTime($clockOut) }}
                    </p>
                </div>

                <div class="rounded-xl border border-gray-200 p-4 dark:border-white/10">
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        {{ $status === 'done' ? 'Total worked' : 'Elapsed' }}
                    </p>
                    <p class="mt-1 text-xl font-bold text-gray-950 dark:text-white">
                        {{ $elapsed ?? '—' }}
                    </p>
                </div>
            </div>

            {{-- Action --}}
            <div class="flex items-center justify-end gap-3">
                @if ($status === 'in_progress')
                    <x-filament::button
                        wire:click="clockOut"
                        wire:confirm="Clock out now?"
                        icon="heroicon-o-stop-circle"
                        size="lg"
                        color="warning"
                    >
                        Clock Out
                    </x-filament::button>
                @elseif ($this->canClockIn())
                    <x-filament::button
                        wire:click="clockIn"
                        icon="heroicon-o-play-circle"
                        size="lg"
                        color="primary"
                    >
                        Clock In
                    </x-filament::button>
                @else
                    <span class="inline-flex items-center gap-1.5 text-sm font-medium text-success-600 dark:text-success-400">
                        <x-filament::icon icon="heroicon-o-check-circle" class="h-5 w-5" />
                        Shift complete — see you next shift
                    </span>
                @endif
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
