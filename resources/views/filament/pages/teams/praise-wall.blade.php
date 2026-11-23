{{--
    The praise wall, drawn for the Microsoft Teams tab.

    Two changes of substance over /admin's version. The cycle now opens with
    a podium — who has actually been praised most — because a wall sorted by
    recency buries that, and it is the thing people scroll looking for. And
    each praise reads as a certificate rather than a row, which is the whole
    point of giving one.

    Everything the admin wall does is still here: the sorts, the reaction,
    the comment count, edit and delete for your own, who sent it, and the
    modals and confetti.
--}}
@php
    use App\Filament\Pages\PraiseWall;
    use App\Models\PraiseSession;

    $praises = $this->getPraises();

    // Top three of the cycle, laid out second–first–third so the winner is
    // the tall one in the middle.
    $ranked = collect($this->podiumForSession(PraiseSession::current(), 3))
        ->filter(fn (array $place): bool => $place['user'] !== null)
        ->values();

    $podium = collect([$ranked->get(1), $ranked->get(0), $ranked->get(2)])
        ->filter()
        ->map(fn (array $place): array => [...$place, 'winner' => $place['rank'] === 1])
        ->values();

    $initials = function (?string $name): string {
        $parts = preg_split('/\s+/', trim((string) $name)) ?: [];

        return strtoupper(implode('', array_map(
            fn (string $part): string => mb_substr($part, 0, 1),
            array_slice(array_filter($parts), 0, 2),
        ))) ?: '?';
    };

    $medals = ['🥇', '🥈', '🥉'];
@endphp

<x-filament-panels::page>
    <x-teams.band title="Praise Wall" :subtitle="$this->cycleLabel()">
        <div class="flex flex-wrap items-center gap-2.5">
            {{ $this->giveAction }}

            @if ($this->canManageCycles())
                {{ $this->finishCycleAction }}
            @endif
        </div>
    </x-teams.band>

    <div class="mx-auto -mt-9 w-full max-w-[1180px] space-y-3.5">
        @include('filament.announcements.teams-banners', ['inline' => true])

        {{-- The podium --}}
        @if ($podium->isNotEmpty())
            <section aria-labelledby="hr-pw-podium" class="rounded-[26px] bg-purple-950 px-[26px] pt-[22px] pb-[26px] text-white shadow-[0_14px_32px_-16px_rgb(31_19_66/0.55)]">
                <h2 id="hr-pw-podium" class="text-base font-bold tracking-[-0.2px]">Most praised this cycle</h2>
                <p class="mt-1 mb-[18px] text-[13px] text-purple-300">{{ $this->cycleLabel() }}</p>

                <div class="grid grid-cols-3 items-end gap-3.5">
                    @foreach ($podium as $place)
                        <div @class([
                            'flex flex-col items-center gap-[9px] rounded-[20px] text-center',
                            'bg-amber-400/15 px-3.5 py-[26px]' => $place['winner'],
                            'bg-white/[0.07] px-3.5 py-[18px]' => ! $place['winner'],
                        ])>
                            <span @class([
                                'flex shrink-0 items-center justify-center rounded-full bg-purple-800 font-bold text-white',
                                'size-[62px] border-[3px] border-amber-400 text-[19px]' => $place['winner'],
                                'size-[52px] border-[3px] border-purple-300 text-base' => ! $place['winner'],
                            ])>{{ $initials($place['user']?->name) }}</span>

                            <span class="text-xl leading-none" aria-hidden="true">{{ $medals[$place['rank'] - 1] ?? '🏅' }}</span>

                            <span class="text-[14.5px] font-bold">{{ $place['user']?->name }}</span>

                            <span class="text-[12.5px] text-purple-200">
                                {{ $place['praises'] }} {{ Str::plural('praise', $place['praises']) }}
                                @if ($place['reactions'] > 0)
                                    · {{ $place['reactions'] }} ❤️
                                @endif
                            </span>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        @if ($praises->isEmpty())
            <section class="hr-card px-6 py-10 text-center">
                <p class="text-sm" style="color: var(--hr-ink-soft)">
                    No praises yet this cycle — hit <span class="font-bold text-purple-950 dark:text-white">Give Praise</span> to recognise a teammate. 🎉
                </p>
            </section>
        @else
            {{-- Sorting --}}
            <div class="flex flex-wrap gap-2">
                @foreach (PraiseWall::SORTS as $value => $label)
                    <button
                        type="button"
                        wire:click="setSort('{{ $value }}')"
                        aria-pressed="{{ $this->sort === $value ? 'true' : 'false' }}"
                        @class([
                            'hr-btn h-[38px] rounded-full px-4 text-[13.5px]',
                            'hr-btn-on' => $this->sort === $value,
                        ])
                    >{{ $label }}</button>
                @endforeach
            </div>

            {{-- The feed --}}
            <div class="grid grid-cols-1 gap-3.5 md:grid-cols-2 xl:grid-cols-3">
                @foreach ($praises as $praise)
                    @php
                        $recipient = $praise->recipient;
                        $avatar = $recipient?->getFilamentAvatarUrl()
                            ?? 'https://ui-avatars.com/api/?name='.urlencode($recipient?->name ?? 'User').'&size=160';
                    @endphp

                    <article class="hr-tile flex flex-col overflow-hidden rounded-[24px] bg-white shadow-[0_12px_28px_-18px_rgb(31_19_66/0.3)] dark:bg-white/5">
                        {{-- The certificate opens the praise; the footer buttons sit
                             outside it so they are not swallowed by the click. --}}
                        <div
                            role="button"
                            tabindex="0"
                            wire:click="mountAction('viewPraise', @js(['praise' => $praise->id]))"
                            wire:keydown.enter="mountAction('viewPraise', @js(['praise' => $praise->id]))"
                            class="flex flex-1 cursor-pointer flex-col focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
                        >
                            <div class="bg-purple-50 px-4 pt-[22px] pb-[17px] text-center dark:bg-white/5">
                                <img
                                    src="{{ $avatar }}"
                                    alt="{{ $recipient?->name }}"
                                    class="mx-auto size-[72px] rounded-full object-cover ring-4 ring-white dark:ring-purple-950"
                                />
                                <p class="mt-[11px] text-[16.5px] font-bold text-purple-950 dark:text-white">
                                    {{ $recipient?->name ?? 'Unknown' }}
                                </p>
                                <p class="mt-0.5 text-[12.5px]" style="color: var(--hr-ink-soft)">
                                    from {{ $praise->senderName() }}
                                </p>
                            </div>

                            <div class="px-5 pt-4 pb-3.5">
                                @if ($praise->badge)
                                    <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-400/20 px-3 py-1 text-[12.5px] font-bold text-amber-900 dark:text-amber-200">
                                        <span aria-hidden="true">{{ $praise->badge->icon ?: '🏅' }}</span>{{ $praise->badge->label }}
                                    </span>
                                @endif

                                <p class="mt-[11px] text-[14px] leading-relaxed whitespace-pre-line" style="color: var(--hr-ink-soft)">
                                    {{ Str::limit($praise->message, 140) }}
                                </p>
                            </div>
                        </div>

                        <div class="mt-auto flex items-center gap-1 border-t px-3.5 pt-2 pb-2.5" style="border-color: var(--hr-ground)">
                            <button
                                type="button"
                                wire:click.stop="toggleReaction({{ $praise->id }})"
                                aria-pressed="{{ $this->hasReacted($praise) ? 'true' : 'false' }}"
                                @class([
                                    'inline-flex h-[34px] items-center gap-[7px] rounded-[11px] px-[11px] text-[13.5px] font-bold transition',
                                    'bg-danger-50 text-danger-700 dark:bg-danger-500/15 dark:text-danger-300' => $this->hasReacted($praise),
                                    'hover:bg-[color:var(--hr-ground)]' => ! $this->hasReacted($praise),
                                ])
                                @if (! $this->hasReacted($praise)) style="color: var(--hr-ink-soft)" @endif
                            >
                                <span class="text-sm" aria-hidden="true">{{ PraiseWall::REACTION }}</span>
                                {{ $praise->reactions->where('type', PraiseWall::REACTION)->count() }}
                            </button>

                            <button
                                type="button"
                                wire:click.stop="mountAction('viewPraise', @js(['praise' => $praise->id]))"
                                class="inline-flex h-[34px] items-center gap-[7px] rounded-[11px] px-[11px] text-[13.5px] font-bold transition hover:bg-[color:var(--hr-ground)]"
                                style="color: var(--hr-ink-soft)"
                            >
                                <span aria-hidden="true">💬</span>
                                {{ $praise->comments->count() }}
                            </button>

                            <span class="flex-1"></span>

                            @if ($this->ownsPraise($praise))
                                <button
                                    type="button"
                                    wire:click.stop="mountAction('editPraise', @js(['praise' => $praise->id]))"
                                    title="Edit praise"
                                    aria-label="Edit this praise"
                                    class="rounded-lg p-1.5 transition hover:text-primary-600"
                                    style="color: var(--hr-ink-soft)"
                                >
                                    <x-filament::icon icon="heroicon-o-pencil-square" class="size-4" />
                                </button>

                                <button
                                    type="button"
                                    wire:click.stop="mountAction('deletePraise', @js(['praise' => $praise->id]))"
                                    title="Delete praise"
                                    aria-label="Delete this praise"
                                    class="rounded-lg p-1.5 transition hover:text-danger-600"
                                    style="color: var(--hr-ink-soft)"
                                >
                                    <x-filament::icon icon="heroicon-o-trash" class="size-4" />
                                </button>
                            @endif

                            <span class="pr-1 text-[12px] whitespace-nowrap" style="color: var(--hr-ink-soft)">
                                {{ $praise->created_at?->diffForHumans(short: true) }}
                            </span>
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    </div>

    <x-filament-actions::modals />

    @include('filament.praise.confetti')
</x-filament-panels::page>
