@php
    $rows = $this->praises();
@endphp

<x-filament-widgets::widget>
    <x-filament::section collapsible id="my-praise" :persist-collapsed="true">
        <x-slot name="heading">✨ Praise you received</x-slot>

        @if ($rows->isEmpty())
            <p class="text-sm text-gray-500 dark:text-gray-400">
                No praise yet — it's coming, keep being awesome.
            </p>
        @else
            <ul class="space-y-3">
                @foreach ($rows as $praise)
                    <li class="border-l-2 border-primary-400 pl-3">
                        <p class="text-sm text-gray-700 dark:text-gray-300">
                            “{{ \Illuminate\Support\Str::limit($praise->message, 120) }}”
                        </p>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            — {{ $praise->senderName() }} · {{ $praise->created_at?->format('j M') }}
                            @if ($praise->badge)
                                <span class="text-gray-400">·</span> {{ trim(($praise->badge->icon ? $praise->badge->icon.' ' : '').$praise->badge->label) }}
                            @endif
                        </p>
                    </li>
                @endforeach
            </ul>

            <a href="{{ $this->praiseWallUrl() }}" class="mt-3 inline-block text-xs font-medium text-primary-600 hover:underline dark:text-primary-400">
                See all praise →
            </a>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
