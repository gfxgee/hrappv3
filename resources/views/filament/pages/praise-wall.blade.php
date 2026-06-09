@php
    use App\Filament\Pages\PraiseWall;
@endphp

<x-filament-panels::page>
    @php($praises = $this->getPraises())

    @if ($praises->isEmpty())
        <x-filament::section>
            <div class="py-8 text-center">
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    No praises yet — hit <span class="font-semibold">Give Praise</span> to recognize a teammate! 🎉
                </p>
            </div>
        </x-filament::section>
    @else
        {{-- Sort / filter --}}
        <div class="mb-4 flex flex-wrap gap-2">
            @foreach (PraiseWall::SORTS as $value => $label)
                <button
                    type="button"
                    wire:click="setSort('{{ $value }}')"
                    @class([
                        'rounded-lg px-3 py-1.5 text-sm font-medium transition',
                        'bg-primary-600 text-white shadow-sm' => $this->sort === $value,
                        'bg-gray-100 text-gray-600 hover:bg-gray-200 dark:bg-white/5 dark:text-gray-300 dark:hover:bg-white/10' => $this->sort !== $value,
                    ])
                >
                    {{ $label }}
                </button>
            @endforeach
        </div>

        <div class="grid grid-cols-1 gap-6 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($praises as $praise)
                @php($recipient = $praise->recipient)
                @php($avatar = $recipient?->getFilamentAvatarUrl()
                    ?? 'https://ui-avatars.com/api/?name=' . urlencode($recipient?->name ?? 'User') . '&size=160')

                <div class="flex flex-col overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900">
                    {{-- Banner + avatar --}}
                    <div class="bg-gradient-to-br from-sky-100 to-teal-100 px-4 pb-4 pt-6 text-center dark:from-sky-500/10 dark:to-teal-500/10">
                        <img
                            src="{{ $avatar }}"
                            alt="{{ $recipient?->name }}"
                            class="mx-auto h-20 w-20 rounded-full object-cover shadow ring-4 ring-white dark:ring-gray-900"
                        />
                        <p class="mt-3 text-base font-bold text-teal-800 dark:text-teal-200">
                            {{ $recipient?->name ?? 'Unknown' }}
                        </p>
                    </div>

                    {{-- Body --}}
                    <div class="flex flex-1 flex-col gap-2 px-4 py-4">
                        @if ($praise->badge)
                            <div class="flex items-center gap-1.5 text-sm font-semibold text-gray-950 dark:text-white">
                                <span>{{ $praise->badge->icon ?: '🏅' }}</span>
                                <span>{{ $praise->badge->label }}</span>
                            </div>
                        @endif

                        <button
                            type="button"
                            wire:click="mountAction('viewPraise', @js(['praise' => $praise->id]))"
                            class="whitespace-pre-line text-left text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
                        >
                            {{ \Illuminate\Support\Str::limit($praise->message, 140) }}
                        </button>
                    </div>

                    {{-- Footer --}}
                    <div class="mt-auto flex items-center justify-between gap-2 border-t border-gray-100 px-4 py-3 dark:border-white/10">
                        <div class="flex items-center gap-1">
                            <button
                                type="button"
                                wire:click="toggleReaction({{ $praise->id }})"
                                @class([
                                    'inline-flex items-center gap-1 rounded-lg px-2 py-1 text-sm font-medium transition',
                                    'text-danger-600 dark:text-danger-400' => $this->hasReacted($praise),
                                    'text-gray-500 hover:text-danger-500 dark:text-gray-400' => ! $this->hasReacted($praise),
                                ])
                            >
                                <span>{{ PraiseWall::REACTION }}</span>
                                <span>{{ $praise->reactions->where('type', PraiseWall::REACTION)->count() }}</span>
                            </button>

                            <button
                                type="button"
                                wire:click="mountAction('viewPraise', @js(['praise' => $praise->id]))"
                                class="inline-flex items-center gap-1 rounded-lg px-2 py-1 text-sm font-medium text-gray-500 transition hover:text-primary-500 dark:text-gray-400"
                            >
                                <span>💬</span>
                                <span>{{ $praise->comments->count() }}</span>
                            </button>
                        </div>

                        <div class="flex items-center gap-2">
                            @if ($this->ownsPraise($praise))
                                <button
                                    type="button"
                                    wire:click="mountAction('editPraise', @js(['praise' => $praise->id]))"
                                    title="Edit praise"
                                    class="text-gray-400 transition hover:text-primary-500"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4">
                                        <path d="M13.586 3.586a2 2 0 1 1 2.828 2.828l-.793.793-2.828-2.828.793-.793ZM11.379 5.793 3 14.172V17h2.828l8.38-8.379-2.83-2.828Z" />
                                    </svg>
                                </button>
                                <button
                                    type="button"
                                    wire:click="mountAction('deletePraise', @js(['praise' => $praise->id]))"
                                    title="Delete praise"
                                    class="text-gray-400 transition hover:text-danger-500"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4">
                                        <path fill-rule="evenodd" d="M8.75 1A2.75 2.75 0 0 0 6 3.75v.443c-.795.077-1.584.176-2.365.298a.75.75 0 1 0 .23 1.482l.149-.022.841 10.518A2.75 2.75 0 0 0 7.596 19h4.807a2.75 2.75 0 0 0 2.742-2.53l.841-10.52.149.023a.75.75 0 0 0 .23-1.482A41.03 41.03 0 0 0 14 4.193V3.75A2.75 2.75 0 0 0 11.25 1h-2.5ZM10 4c.84 0 1.673.025 2.5.075V3.75c0-.69-.56-1.25-1.25-1.25h-2.5c-.69 0-1.25.56-1.25 1.25v.325C8.327 4.025 9.16 4 10 4ZM8.58 7.72a.75.75 0 0 0-1.5.06l.3 7.5a.75.75 0 1 0 1.5-.06l-.3-7.5Zm4.34.06a.75.75 0 1 0-1.5-.06l-.3 7.5a.75.75 0 1 0 1.5.06l.3-7.5Z" clip-rule="evenodd" />
                                    </svg>
                                </button>
                            @endif

                            <span class="truncate text-xs text-gray-400">
                                Submitted by {{ $praise->senderName() }}
                            </span>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    <x-filament-actions::modals />

    @assets
        <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.9.3/dist/confetti.browser.min.js" defer></script>
    @endassets

    @script
        <script>
            // A celebratory burst from the lower-center — used when a praise is posted.
            window.boomConfetti = function () {
                if (typeof confetti !== 'function') return;

                const count = 200;
                const defaults = { origin: { y: 0.6 }, zIndex: 99999 };
                const fire = (ratio, opts) => confetti({
                    ...defaults,
                    ...opts,
                    particleCount: Math.floor(count * ratio),
                });

                fire(0.25, { spread: 26, startVelocity: 55 });
                fire(0.2, { spread: 60 });
                fire(0.35, { spread: 100, decay: 0.91, scalar: 0.8 });
                fire(0.1, { spread: 120, startVelocity: 25, decay: 0.92, scalar: 1.2 });
                fire(0.1, { spread: 120, startVelocity: 45 });
            };

            // A gentle shower from the top — used when finishing a cycle.
            window.fallingConfetti = function () {
                if (typeof confetti !== 'function') return;

                const end = Date.now() + 2500;
                (function frame() {
                    confetti({
                        particleCount: 4,
                        startVelocity: 0,
                        ticks: 220,
                        gravity: 0.6,
                        spread: 80,
                        zIndex: 99999,
                        origin: { x: Math.random(), y: -0.1 },
                    });

                    if (Date.now() < end) requestAnimationFrame(frame);
                })();
            };

            $wire.on('praise-confetti', () => window.boomConfetti());
        </script>
    @endscript
</x-filament-panels::page>
