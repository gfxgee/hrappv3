@php
    $rows = $this->holidays();
@endphp

<x-filament-widgets::widget>
    <x-filament::section collapsible id="upcoming-holidays" :persist-collapsed="true">
        <x-slot name="heading">📅 Upcoming Holidays</x-slot>

        @if ($rows->isEmpty())
            <p class="text-sm text-gray-500 dark:text-gray-400">
                No holidays in the next {{ \App\Filament\Widgets\UpcomingHolidaysWidget::WINDOW_DAYS }} days.
            </p>
        @else
            <ul class="space-y-3">
                @foreach ($rows as $row)
                    <li>
                        <button
                            type="button"
                            wire:click="mountAction('viewHoliday', { holiday: {{ $row['id'] }} })"
                            class="flex w-full items-center gap-3 py-3 text-left transition first:pt-0 last:pb-0 pb-5 hover:opacity-75 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 rounded-lg"
                        >
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-purple-100 text-purple-700 dark:bg-purple-500/20 dark:text-purple-200">
                                {{ $row['emoji'] ?: '🎉' }}
                            </span>

                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium text-gray-950 dark:text-white">{{ $row['name'] }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    {{ $row['day'] }}, {{ $row['date']->format('M j, Y') }}
                                    <span class="text-gray-400">·</span> {{ $row['duration'] }}
                                </p>
                            </div>

                            @if ($row['isToday'])
                                <span class="shrink-0 rounded-full bg-purple-100 px-2 py-0.5 text-xs font-semibold text-purple-700 dark:bg-purple-500/20 dark:text-purple-200">
                                    Today
                                </span>
                            @else
                                <span class="shrink-0 text-xs text-gray-400">
                                    in {{ $row['days'] }} {{ \Illuminate\Support\Str::plural('day', $row['days']) }}
                                </span>
                            @endif
                        </button>
                    </li>
                @endforeach
            </ul>
        @endif

        <x-filament-actions::modals />
    </x-filament::section>
</x-filament-widgets::widget>
