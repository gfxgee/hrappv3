@php
    $rows = $this->entries();
@endphp

<x-filament-widgets::widget>
    <x-filament::section collapsible id="coming-up" :persist-collapsed="true">
        <x-slot name="heading">🗓️ Coming up</x-slot>

        @if ($rows->isEmpty())
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Nothing in the next {{ $this->windowDays }} days.
            </p>
        @else
            <ul class="space-y-2">
                @foreach ($rows as $row)
                    @php($isHoliday = $row['holidayId'] !== null)

                    <li>
                        <{{ $isHoliday ? 'button' : 'div' }}
                            @if ($isHoliday)
                                type="button"
                                wire:click="mountAction('viewHoliday', { holiday: {{ $row['holidayId'] }} })"
                            @endif
                            class="flex w-full items-center gap-3 py-2.5 text-left first:pt-0 last:pb-0 {{ $isHoliday ? 'transition hover:opacity-75 focus:outline-none' : '' }}"
                        >
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-gray-100 dark:bg-white/10">
                                {{ $row['emoji'] }}
                            </span>

                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium text-gray-950 dark:text-white">{{ $row['label'] }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    {{ $row['date']->format('D, M j') }}
                                    @if ($row['sub'])
                                        <span class="text-gray-400">·</span> {{ $row['sub'] }}
                                    @endif
                                </p>
                            </div>

                            @if ($row['isToday'])
                                <span class="shrink-0 rounded-full bg-primary-100 px-2 py-0.5 text-xs font-semibold text-primary-700 dark:bg-primary-500/20 dark:text-primary-200">
                                    Today
                                </span>
                            @else
                                <span class="shrink-0 text-xs text-gray-400">
                                    in {{ $row['days'] }} {{ \Illuminate\Support\Str::plural('day', $row['days']) }}
                                </span>
                            @endif
                        </{{ $isHoliday ? 'button' : 'div' }}>
                    </li>
                @endforeach
            </ul>
        @endif

        <x-filament-actions::modals />
    </x-filament::section>
</x-filament-widgets::widget>
