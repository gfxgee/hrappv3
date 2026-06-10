@php
    $rows = $this->requests();
@endphp

<x-filament-widgets::widget>
    <x-filament::section collapsible id="my-requests" :persist-collapsed="true">
        <x-slot name="heading">📋 My requests</x-slot>

        @if ($rows->isEmpty())
            <p class="text-sm text-gray-500 dark:text-gray-400">
                No leave or overtime requests yet.
            </p>
        @else
            <ul class="divide-y divide-gray-100 dark:divide-white/10">
                @foreach ($rows as $row)
                    <li class="flex items-center gap-3 py-2.5 first:pt-0 last:pb-0">
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium text-gray-950 dark:text-white">
                                {{ $row['label'] }} · {{ $row['dates'] }}
                            </p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                Submitted {{ $row['submitted']?->format('j M') }}
                            </p>
                        </div>

                        <x-filament::badge :color="$row['status']->color()">
                            {{ $row['status']->label() }}
                        </x-filament::badge>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
