@php
    $rows = $this->members();
@endphp

<x-filament-widgets::widget>
    <x-filament::section collapsible id="my-team-today" :persist-collapsed="true">
        <x-slot name="heading">👥 My team today</x-slot>

        @if (! $this->hasDepartment())
            <p class="text-sm text-gray-500 dark:text-gray-400">
                You're not assigned to a department yet.
            </p>
        @elseif ($rows->isEmpty())
            <p class="text-sm text-gray-500 dark:text-gray-400">
                No other teammates in your department.
            </p>
        @else
            <ul class="divide-y divide-gray-100 dark:divide-white/10">
                @foreach ($rows as $row)
                    @php($member = $row['user'])
                    @php($avatar = $member->getFilamentAvatarUrl()
                        ?? 'https://ui-avatars.com/api/?name=' . urlencode($member->name) . '&size=80')

                    <li class="flex items-center gap-3 py-2.5 first:pt-0 last:pb-0">
                        <img src="{{ $avatar }}" alt="{{ $member->name }}" class="h-9 w-9 shrink-0 rounded-full object-cover" />

                        <p class="min-w-0 flex-1 truncate text-sm font-medium text-gray-950 dark:text-white">
                            {{ $member->name }}
                        </p>

                        @if ($row['status'] === '—')
                            <span class="text-xs text-gray-400">—</span>
                        @else
                            <x-filament::badge :color="$row['color']">
                                {{ $row['status'] }}
                            </x-filament::badge>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
