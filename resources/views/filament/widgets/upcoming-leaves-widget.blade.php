@php
    $rows = $this->leaves();
@endphp

<x-filament-widgets::widget>
    <x-filament::section collapsible id="upcoming-leaves" :persist-collapsed="true">
        <x-slot name="heading">🌴 Upcoming Leaves</x-slot>

        @if ($rows->isEmpty())
            <p class="text-sm text-gray-500 dark:text-gray-400">
                No upcoming leaves in the next {{ $this->windowDays() }} days.
            </p>
        @else
            <ul class="divide-y divide-gray-100 dark:divide-white/10">
                @foreach ($rows as $row)
                    @php($leave = $row['request'])
                    <li class="space-y-1 py-2.5 first:pt-0 last:pb-0">
                        <div class="flex items-center justify-between gap-2">
                            <p class="truncate text-sm font-medium text-gray-950 dark:text-white">
                                {{ $leave->user?->name ?? 'Unknown' }}
                            </p>
                            <x-filament::badge :color="$leave->status?->color()">
                                {{ $leave->status?->label() }}
                            </x-filament::badge>
                        </div>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            {{ $leave->request_type?->label() }}
                            <span class="text-gray-400">·</span>
                            {{ $leave->start_date?->format('M j') }}@if ($leave->start_date && $leave->end_date && ! $leave->start_date->isSameDay($leave->end_date)) – {{ $leave->end_date->format('M j') }}@endif
                            <span class="text-gray-400">·</span>
                            in {{ $row['days'] }} {{ \Illuminate\Support\Str::plural('day', $row['days']) }}
                        </p>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
