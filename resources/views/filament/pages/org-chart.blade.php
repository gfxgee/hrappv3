@php
    $data = $this->chartData();
    $nodes = $data['nodes'];
    $unassigned = $data['unassigned'];
@endphp

@vite('resources/js/org-chart.js')

<x-filament-panels::page>
    @if (empty($nodes))
        <x-filament::section>
            <div class="py-10 text-center">
                <p class="text-base font-semibold text-gray-950 dark:text-white">
                    No org chart yet
                </p>
                <p class="mx-auto mt-1 max-w-md text-sm text-gray-500 dark:text-gray-400">
                    Mark someone as the <strong>company head</strong> on their
                    employee profile (Employment → “Top of org chart”), then set
                    each person’s manager. The chart builds itself from there.
                </p>
            </div>
        </x-filament::section>
    @else
        <div
            x-data="orgChart(@js($nodes))"
            class="space-y-4"
        >
            <div class="flex flex-wrap items-center justify-between gap-3">
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Drag to pan, scroll to zoom. Click a node to expand or collapse its team.
                </p>
                <div class="flex items-center gap-2">
                    <x-filament::button color="gray" size="sm" x-on:click="expandAll()">
                        Expand all
                    </x-filament::button>
                    <x-filament::button color="gray" size="sm" x-on:click="collapseAll()">
                        Collapse all
                    </x-filament::button>
                    <x-filament::button color="gray" size="sm" x-on:click="fit()">
                        Fit
                    </x-filament::button>
                </div>
            </div>

            <div
                x-ref="canvas"
                class="h-[70vh] w-full overflow-hidden rounded-xl border border-gray-200 bg-gray-50 dark:border-white/10 dark:bg-white/5"
            ></div>
        </div>
    @endif

    @if (! empty($unassigned))
        <x-filament::section
            collapsible
            :collapsed="! empty($nodes)"
            icon="heroicon-o-user-group"
        >
            <x-slot name="heading">Unassigned ({{ count($unassigned) }})</x-slot>
            <x-slot name="description">
                Employees who aren’t connected to the chart yet — set a manager on their profile to place them.
            </x-slot>

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($unassigned as $person)
                    <div class="flex items-center gap-3 rounded-xl border border-gray-200 p-3 dark:border-white/10">
                        @if ($person['imageUrl'])
                            <img src="{{ $person['imageUrl'] }}" alt="" class="h-10 w-10 flex-none rounded-full object-cover" />
                        @else
                            <span class="flex h-10 w-10 flex-none items-center justify-center rounded-full bg-[#271A3D] text-sm font-bold text-white">
                                {{ $person['initials'] }}
                            </span>
                        @endif
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold text-gray-950 dark:text-white">{{ $person['name'] }}</p>
                            <p class="truncate text-xs text-gray-500 dark:text-gray-400">
                                {{ $person['title'] ?: '—' }}{{ $person['department'] ? ' · '.$person['department'] : '' }}
                            </p>
                        </div>
                    </div>
                @endforeach
            </div>
        </x-filament::section>
    @endif
</x-filament-panels::page>
