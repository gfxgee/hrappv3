@php
    $items = $this->equipment();
@endphp

<x-filament-widgets::widget>
    <x-filament::section collapsible id="my-equipment" :persist-collapsed="true">
        <x-slot name="heading">💻 My equipment</x-slot>

        @if ($items->isEmpty())
            <p class="text-sm text-gray-500 dark:text-gray-400">
                No equipment is currently assigned to you.
            </p>
        @else
            <ul class="divide-y divide-gray-100 dark:divide-white/10">
                @foreach ($items as $item)
                    <li class="flex items-center gap-3 py-2.5 first:pt-0 last:pb-0">
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium text-gray-950 dark:text-white">
                                {{ $item['category'] }} · {{ $item['name'] }}
                            </p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                @if ($item['tag'])
                                    {{ $item['tag'] }}
                                @endif
                                @if ($item['since'])
                                    <span class="text-gray-400">·</span> since {{ $item['since']->format('j M Y') }}
                                @endif
                                @if ($item['due'])
                                    <span class="text-gray-400">·</span> due back {{ $item['due']->format('j M Y') }}
                                @endif
                            </p>
                        </div>

                        @if ($item['type'])
                            <x-filament::badge :color="$item['typeColor']">
                                {{ $item['type'] }}
                            </x-filament::badge>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
