@php
    $today = today()->toDateString();
    $current = $this->todaysMood();
    $notoBase = 'https://fonts.gstatic.com/s/e/notoemoji/latest';
@endphp

<style>
    /* Hide the animated player until the web component is defined, and show the
       plain-emoji fallback in the meantime (or if the CDN never loads). */
    lottie-player:not(:defined) { display: none; }
    lottie-player:defined + .mood-fallback { display: none; }
    .mood-emoji-slot { display: inline-flex; align-items: center; justify-content: center; }
</style>

<div
    x-data="{
        open: false,
        dismissKey: 'mood-checkin-dismissed-{{ $today }}',
        init() {
            if (! window.__notoLottieLoaded) {
                window.__notoLottieLoaded = true;
                const script = document.createElement('script');
                script.src = 'https://unpkg.com/@lottiefiles/lottie-player@2.0.12/dist/lottie-player.js';
                script.async = true;
                document.head.appendChild(script);
            }
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
        playEmoji(el) {
            if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                return;
            }
            el.querySelector('lottie-player')?.play();
        },
        stopEmoji(el) {
            el.querySelector('lottie-player')?.stop();
        },
    }"
    x-on:mood-logged.window="open = false"
>
    {{-- Floating bubble (bottom-right) — always available to (re)open the picker --}}
    <button
        type="button"
        x-on:click="open = true"
        x-on:mouseenter="playEmoji($el)"
        x-on:mouseleave="stopEmoji($el)"
        class="fixed bottom-6 right-6 z-40 flex h-14 w-14 items-center justify-center rounded-full bg-white text-2xl shadow-lg ring-1 ring-gray-950/10 transition hover:scale-105 hover:shadow-xl dark:bg-gray-800 dark:ring-white/10"
        aria-label="Mood check-in"
        title="{{ $current ? 'Update your mood' : 'How are you feeling today?' }}"
    >
        <span class="mood-emoji-slot h-9 w-9">
            <lottie-player
                src="{{ $notoBase }}/{{ $current?->lottieCodepoint() ?? '1f60a' }}/lottie.json"
                background="transparent"
                speed="1"
                loop
                style="width: 100%; height: 100%;"
            ></lottie-player>
            <span class="mood-fallback">{{ $current?->emoji() ?? '😊' }}</span>
        </span>
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
                        x-on:mouseenter="playEmoji($el)"
                        x-on:mouseleave="stopEmoji($el)"
                        @class([
                            'flex flex-col items-center gap-1.5 rounded-xl border p-3 transition hover:-translate-y-0.5 hover:border-primary-400 hover:bg-primary-50 dark:hover:bg-primary-500/10',
                            'border-primary-500 bg-primary-50 dark:border-primary-400 dark:bg-primary-500/10' => $current?->value === $mood['value'],
                            'border-gray-200 dark:border-white/10' => $current?->value !== $mood['value'],
                        ])
                    >
                        <span class="mood-emoji-slot h-10 w-10 text-3xl">
                            <lottie-player
                                src="{{ $notoBase }}/{{ $mood['lottie'] }}/lottie.json"
                                background="transparent"
                                speed="1"
                                loop
                                style="width: 100%; height: 100%;"
                            ></lottie-player>
                            <span class="mood-fallback">{{ $mood['emoji'] }}</span>
                        </span>
                        <span class="text-xs font-medium text-gray-700 dark:text-gray-300">{{ $mood['label'] }}</span>
                    </button>
                @endforeach
            </div>
        </div>
    </div>
</div>
