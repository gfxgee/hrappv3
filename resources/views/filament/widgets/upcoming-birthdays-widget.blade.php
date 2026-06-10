@php
    $rows = $this->birthdays();
@endphp

<x-filament-widgets::widget>
    <x-filament::section collapsible id="upcoming-birthdays" :persist-collapsed="true">
        <x-slot name="heading">🎂 Upcoming Birthdays</x-slot>

        @if ($rows->isEmpty())
            <p class="text-sm text-gray-500 dark:text-gray-400">
                No birthdays in the next {{ $this->windowDays() }} days.
            </p>
        @else
            <ul class="divide-y divide-gray-100 dark:divide-white/10">
                @foreach ($rows as $row)
                    @php($employee = $row['user'])
                    @php($avatar = $employee->getFilamentAvatarUrl()
                        ?? 'https://ui-avatars.com/api/?name=' . urlencode($employee->name) . '&size=80')

                    <li class="flex items-center gap-3 py-2 first:pt-0 last:pb-0">
                        <img src="{{ $avatar }}" alt="{{ $employee->name }}" class="h-9 w-9 shrink-0 rounded-full object-cover" />

                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium text-gray-950 dark:text-white">{{ $employee->name }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $row['date']->format('M j') }}</p>
                        </div>

                        @if ($row['isToday'])
                            <span class="shrink-0 rounded-full bg-yellow-100 px-2 py-0.5 text-xs font-semibold text-yellow-700 dark:bg-yellow-500/20 dark:text-yellow-300">
                                Today 🎉
                            </span>
                        @else
                            <span class="shrink-0 text-xs text-gray-400">
                                in {{ $row['days'] }} {{ \Illuminate\Support\Str::plural('day', $row['days']) }}
                            </span>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
