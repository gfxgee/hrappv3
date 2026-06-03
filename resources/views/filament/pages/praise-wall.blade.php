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

                        <span class="truncate text-xs text-gray-400">
                            Submitted by {{ $praise->senderName() }}
                        </span>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    <x-filament-actions::modals />
</x-filament-panels::page>
