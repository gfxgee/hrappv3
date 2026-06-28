@php
    $events = $this->events();
@endphp

<x-filament-widgets::widget>
    <x-filament::section collapsible id="team-activity" :persist-collapsed="true">
        <x-slot name="heading">📣 Activity today</x-slot>

        @if ($events->isEmpty())
            <p class="text-sm text-gray-500 dark:text-gray-400">
                No activity yet today.
            </p>
        @else
            <ul class="divide-y divide-gray-100 dark:divide-white/10">
                @foreach ($events as $event)
                    <li class="flex items-center gap-3 py-2.5 first:pt-0 last:pb-0">
                        <span class="text-lg leading-none">{{ $event['icon'] }}</span>
                        <p class="min-w-0 flex-1 truncate text-sm text-gray-950 dark:text-white">
                            {{ $event['text'] }}
                        </p>
                        <span class="shrink-0 text-xs text-gray-500 dark:text-gray-400">
                            {{ $event['time']?->format('g:i A') }}
                        </span>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
