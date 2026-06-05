@php
    $entries = collect($podium);
    $first = $entries->firstWhere('rank', 1);
    $second = $entries->firstWhere('rank', 2);
    $third = $entries->firstWhere('rank', 3);
    $rest = $entries->where('rank', '>', 3);

    $avatarFor = fn ($user) => $user?->getFilamentAvatarUrl()
        ?? 'https://ui-avatars.com/api/?name=' . urlencode($user?->name ?? 'User') . '&size=160';
@endphp

@if ($entries->isEmpty())
    <p class="py-8 text-center text-sm text-gray-500 dark:text-gray-400">
        No praises in this cycle yet — there's nobody to crown.
    </p>
@else
    <div class="space-y-6">
        @if ($cycleName)
            <p class="text-center text-sm text-gray-500 dark:text-gray-400">
                Winners of <span class="font-semibold text-gray-950 dark:text-white">{{ $cycleName }}</span>
            </p>
        @endif

        {{-- Podium (2nd · 1st · 3rd) --}}
        <div class="flex items-end justify-center gap-3">
            @foreach ([
                ['entry' => $second, 'medal' => '🥈', 'bar' => 'h-16', 'bg' => 'bg-gray-100 dark:bg-white/10', 'ring' => 'ring-gray-300'],
                ['entry' => $first,  'medal' => '🥇', 'bar' => 'h-24', 'bg' => 'bg-amber-100 dark:bg-amber-500/20', 'ring' => 'ring-amber-400'],
                ['entry' => $third,  'medal' => '🥉', 'bar' => 'h-12', 'bg' => 'bg-orange-100 dark:bg-orange-500/20', 'ring' => 'ring-orange-300'],
            ] as $slot)
                @continue(! $slot['entry'])
                @php($user = $slot['entry']['user'])

                <div class="flex w-24 flex-col items-center">
                    <div class="text-2xl leading-none">{{ $slot['medal'] }}</div>
                    <img src="{{ $avatarFor($user) }}" alt="{{ $user?->name }}"
                         class="mt-1 h-14 w-14 rounded-full object-cover ring-4 {{ $slot['ring'] }}" />
                    <p class="mt-1 w-full truncate text-center text-xs font-semibold text-gray-950 dark:text-white">
                        {{ $user?->name ?? 'Unknown' }}
                    </p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">❤️ {{ $slot['entry']['reactions'] }}</p>
                    <div class="mt-1 flex w-full items-start justify-center rounded-t-lg pt-1 text-sm font-bold text-gray-500 dark:text-gray-300 {{ $slot['bg'] }} {{ $slot['bar'] }}">
                        #{{ $slot['entry']['rank'] }}
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Runners-up --}}
        @if ($rest->isNotEmpty())
            <div class="space-y-1">
                @foreach ($rest as $entry)
                    <div class="flex items-center justify-between rounded-lg bg-gray-50 px-3 py-1.5 text-sm dark:bg-white/5">
                        <div class="flex items-center gap-2">
                            <span class="w-5 text-center text-xs font-semibold text-gray-400">#{{ $entry['rank'] }}</span>
                            <span class="text-gray-800 dark:text-gray-200">{{ $entry['user']?->name ?? 'Unknown' }}</span>
                        </div>
                        <span class="text-xs text-gray-500 dark:text-gray-400">
                            ❤️ {{ $entry['reactions'] }} · {{ $entry['praises'] }} {{ \Illuminate\Support\Str::plural('praise', $entry['praises']) }}
                        </span>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
@endif
