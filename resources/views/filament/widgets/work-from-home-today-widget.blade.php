@php
    $rows = $this->entries();
@endphp

<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">🏠 Work From Home Today</x-slot>

        @if ($rows->isEmpty())
            <p class="text-sm text-gray-500 dark:text-gray-400">No one is working from home today.</p>
        @else
            <ul class="divide-y divide-gray-100 dark:divide-white/10">
                @foreach ($rows as $leave)
                    @php($employee = $leave->user)
                    @php($avatar = $employee?->getFilamentAvatarUrl()
                        ?? 'https://ui-avatars.com/api/?name=' . urlencode($employee?->name ?? 'User') . '&size=80')

                    <li class="flex items-center gap-3 py-2 first:pt-0 last:pb-0">
                        <img src="{{ $avatar }}" alt="{{ $employee?->name }}" class="h-9 w-9 shrink-0 rounded-full object-cover" />

                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium text-gray-950 dark:text-white">{{ $employee?->name ?? 'Unknown' }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                @if ($leave->start_time && $leave->end_time)
                                    {{ $leave->start_time }} – {{ $leave->end_time }}
                                @else
                                    All day
                                @endif
                            </p>
                        </div>

                        <x-filament::badge :color="$leave->status?->color()">
                            {{ $leave->status?->label() }}
                        </x-filament::badge>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
