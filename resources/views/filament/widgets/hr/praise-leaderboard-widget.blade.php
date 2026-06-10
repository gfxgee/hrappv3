@php
    $rows = $this->leaders();
@endphp

<x-filament-widgets::widget>
    <x-filament::section collapsible id="praise-leaderboard" :persist-collapsed="true">
        <x-slot name="heading">✨ Praise leaderboard</x-slot>
        <x-slot name="description">{{ $this->cycleName() }}</x-slot>

        @if ($rows === [])
            <p class="text-sm text-gray-500 dark:text-gray-400">
                No praise yet this cycle.
            </p>
        @else
            <ul class="divide-y divide-gray-100 dark:divide-white/10">
                @foreach ($rows as $row)
                    @php($member = $row['user'])
                    @php($avatar = $member?->getFilamentAvatarUrl()
                        ?? 'https://ui-avatars.com/api/?name=' . urlencode($member?->name ?? 'User') . '&size=80')

                    <li class="flex items-center gap-3 py-2.5 first:pt-0 last:pb-0">
                        <span class="w-5 shrink-0 text-center text-sm font-bold {{ $row['rank'] === 1 ? 'text-amber-500' : 'text-gray-400' }}">
                            {{ $row['rank'] }}
                        </span>

                        <img src="{{ $avatar }}" alt="{{ $member?->name }}" class="h-8 w-8 shrink-0 rounded-full object-cover" />

                        <p class="min-w-0 flex-1 truncate text-sm font-medium text-gray-950 dark:text-white">
                            {{ $member?->name ?? '—' }}
                        </p>

                        <span class="shrink-0 text-xs text-gray-500 dark:text-gray-400">
                            ❤️ {{ $row['reactions'] }}
                        </span>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
