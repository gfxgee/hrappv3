@php
    $today = today()->toDateString();
    $current = $this->todaysMood();
@endphp

<div
    x-data="{
        open: false,
        dismissKey: 'mood-checkin-dismissed-{{ $today }}',
        init() {
            @if (! $current)
                if (sessionStorage.getItem(this.dismissKey) !== '1') {
                    this.open = true;
                }
            @endif
        },
        dismiss() {
            this.open = false;
            sessionStorage.setItem(this.dismissKey, '1');
        },
    }"
    x-on:mood-logged.window="open = false"
>
    {{-- Floating bubble (bottom-right) — always available to (re)open the picker --}}
    <button
        type="button"
        x-on:click="open = true"
        class="fixed bottom-6 right-6 z-40 flex h-14 w-14 items-center justify-center rounded-full bg-white text-2xl shadow-lg ring-1 ring-gray-950/10 transition hover:scale-105 hover:shadow-xl dark:bg-gray-800 dark:ring-white/10"
        aria-label="Mood check-in"
        title="{{ $current ? 'Update your mood' : 'How are you feeling today?' }}"
    >
        <span>{{ $current?->emoji() ?? '😊' }}</span>
    </button>

    {{-- Modal --}}
    <div
        x-show="open"
        x-cloak
        x-transition.opacity
        class="fixed inset-0 z-50 flex items-center justify-center p-4"
        role="dialog"
        aria-modal="true"
        aria-labelledby="mood-modal-title"
    >
        {{-- Backdrop --}}
        <div
            x-show="open"
            x-transition.opacity
            x-on:click="dismiss()"
            class="absolute inset-0 bg-gray-950/50 backdrop-blur-sm"
        ></div>

        {{-- Card --}}
        <div
            x-show="open"
            x-transition
            class="relative w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10"
        >
            <button
                type="button"
                x-on:click="dismiss()"
                class="absolute right-4 top-4 rounded-lg p-1 text-gray-400 transition hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-white/5 dark:hover:text-gray-200"
                aria-label="Dismiss"
            >
                <x-filament::icon icon="heroicon-o-x-mark" class="h-5 w-5" />
            </button>

            <h2 id="mood-modal-title" class="text-lg font-bold text-gray-950 dark:text-white">
                @if ($current)
                    You're feeling {{ $current->label() }} {{ $current->emoji() }}
                @else
                    How are you feeling today?
                @endif
            </h2>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                @if ($current)
                    Tap a mood to update your check-in.
                @else
                    Your check-in helps us look after the team. Pick the one that fits.
                @endif
            </p>

            <div class="mt-5 grid grid-cols-5 gap-2">
                @foreach ($this->moods() as $mood)
                    <button
                        type="button"
                        wire:click="logMood('{{ $mood['value'] }}')"
                        @class([
                            'flex flex-col items-center gap-1.5 rounded-xl border p-3 transition hover:-translate-y-0.5 hover:border-primary-400 hover:bg-primary-50 dark:hover:bg-primary-500/10',
                            'border-primary-500 bg-primary-50 dark:border-primary-400 dark:bg-primary-500/10' => $current?->value === $mood['value'],
                            'border-gray-200 dark:border-white/10' => $current?->value !== $mood['value'],
                        ])
                    >
                        <span class="text-3xl">{{ $mood['emoji'] }}</span>
                        <span class="text-xs font-medium text-gray-700 dark:text-gray-300">{{ $mood['label'] }}</span>
                    </button>
                @endforeach
            </div>
        </div>
    </div>
</div>
