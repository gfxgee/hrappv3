@php
    use App\Filament\Pages\PraiseWall;
@endphp

@if ($praise)
    @php($recipient = $praise->recipient)
    @php($avatar = $recipient?->getFilamentAvatarUrl()
        ?? 'https://ui-avatars.com/api/?name=' . urlencode($recipient?->name ?? 'User') . '&size=160')

    <div class="space-y-4">
        {{-- Post --}}
        <div class="flex items-start gap-3">
            <img src="{{ $avatar }}" alt="{{ $recipient?->name }}"
                 class="h-12 w-12 shrink-0 rounded-full object-cover ring-2 ring-white dark:ring-gray-900" />
            <div class="min-w-0">
                <p class="font-semibold text-gray-950 dark:text-white">{{ $recipient?->name ?? 'Unknown' }}</p>
                <p class="text-xs text-gray-400">
                    Submitted by {{ $praise->senderName() }} · {{ $praise->created_at->diffForHumans() }}
                </p>
            </div>
        </div>

        @if ($praise->badge)
            <div class="flex items-center gap-1.5 text-sm font-semibold text-gray-950 dark:text-white">
                <span>{{ $praise->badge->icon ?: '🏅' }}</span>
                <span>{{ $praise->badge->label }}</span>
            </div>
        @endif

        <p class="whitespace-pre-line text-gray-700 dark:text-gray-300">{{ $praise->message }}</p>

        {{-- Reactions --}}
        <div class="flex items-center gap-3 border-y border-gray-100 py-2 dark:border-white/10">
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

            <span class="text-sm text-gray-400">
                {{ $praise->comments->count() }} {{ \Illuminate\Support\Str::plural('comment', $praise->comments->count()) }}
            </span>
        </div>

        {{-- Comments --}}
        @if ($praise->comments->isEmpty())
            <p class="text-sm text-gray-400">No comments yet — be the first.</p>
        @else
            <div class="space-y-3">
                @foreach ($praise->comments as $comment)
                    <div class="rounded-lg bg-gray-50 px-3 py-2 dark:bg-white/5">
                        <p class="text-sm font-medium text-gray-950 dark:text-white">{{ $comment->user?->name ?? 'Unknown' }}</p>
                        <p class="text-sm text-gray-600 dark:text-gray-300">{{ $comment->comment }}</p>
                        <p class="mt-0.5 text-xs text-gray-400">{{ $comment->created_at->diffForHumans() }}</p>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
@endif
