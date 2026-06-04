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
            <div class="max-h-[360px] space-y-3 overflow-y-auto pr-1">
                @foreach ($praise->comments as $comment)
                    <div class="group rounded-lg bg-gray-50 px-3 py-2 dark:bg-white/5" wire:key="comment-{{ $comment->id }}">
                        <div class="flex items-start justify-between gap-2">
                            <p class="text-sm font-medium text-gray-950 dark:text-white">{{ $comment->user?->name ?? 'Unknown' }}</p>

                            @if ($this->ownsComment($comment) && $this->editingCommentId !== $comment->id)
                                <div class="flex shrink-0 items-center gap-2 opacity-0 transition group-hover:opacity-100">
                                    <button type="button" wire:click="editComment({{ $comment->id }})"
                                            class="text-xs font-medium text-gray-500 hover:text-primary-600 dark:text-gray-400">
                                        Edit
                                    </button>
                                    <button type="button" wire:click="deleteComment({{ $comment->id }})"
                                            wire:confirm="Delete this comment?"
                                            class="text-xs font-medium text-gray-500 hover:text-danger-600 dark:text-gray-400">
                                        Delete
                                    </button>
                                </div>
                            @endif
                        </div>

                        @if ($this->editingCommentId === $comment->id)
                            {{-- Inline edit form --}}
                            <div class="mt-1 space-y-2">
                                <textarea
                                    wire:model="editingCommentText"
                                    rows="2"
                                    class="block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-white/20 dark:bg-white/5 dark:text-white"
                                ></textarea>
                                <div class="flex items-center gap-2">
                                    <button type="button" wire:click="updateComment({{ $comment->id }})"
                                            class="rounded-lg bg-primary-600 px-2.5 py-1 text-xs font-semibold text-white hover:bg-primary-500">
                                        Save
                                    </button>
                                    <button type="button" wire:click="cancelEditComment"
                                            class="text-xs font-medium text-gray-500 hover:underline dark:text-gray-400">
                                        Cancel
                                    </button>
                                </div>
                            </div>
                        @else
                            @if (filled($comment->comment))
                                <p class="text-sm text-gray-600 dark:text-gray-300">{{ $comment->comment }}</p>
                            @endif
                            @if ($comment->gif_url)
                                <img src="{{ $comment->gif_url }}" alt="GIF"
                                     loading="lazy"
                                     class="mt-1 max-h-40 rounded-lg object-contain" />
                            @endif
                            <p class="mt-0.5 text-xs text-gray-400">{{ $comment->created_at->diffForHumans() }}</p>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif

        {{-- Composer (stays open; posting does not close the modal) --}}
        <div class="space-y-2 border-t border-gray-100 pt-3 dark:border-white/10">
            <div class="flex items-center justify-between">
                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Write a comment</label>

                @if ($this->canEnhanceComment())
                    <div class="flex items-center gap-2">
                        <button type="button" wire:click="enhanceNewComment('polish')"
                                wire:loading.attr="disabled" wire:target="enhanceNewComment"
                                class="inline-flex items-center gap-1 text-xs font-medium text-primary-600 hover:underline dark:text-primary-400">
                            ✨ Polish
                        </button>
                        <button type="button" wire:click="enhanceNewComment('expand')"
                                wire:loading.attr="disabled" wire:target="enhanceNewComment"
                                class="inline-flex items-center gap-1 text-xs font-medium text-primary-600 hover:underline dark:text-primary-400">
                            ↔ Expand
                        </button>
                    </div>
                @endif
            </div>

            <textarea
                wire:model="newComment"
                rows="2"
                maxlength="1000"
                placeholder="Add a comment…"
                class="block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-white/20 dark:bg-white/5 dark:text-white"
            ></textarea>

            <div class="flex items-end justify-between gap-3">
                @include('filament.praise.gif-picker')

                <button
                    type="button"
                    wire:click="postComment({{ $praise->id }})"
                    wire:loading.attr="disabled"
                    wire:target="postComment"
                    class="inline-flex shrink-0 items-center gap-1.5 rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-500 disabled:opacity-60"
                >
                    <x-filament::loading-indicator wire:loading wire:target="postComment" class="h-4 w-4" />
                    Post Comment
                </button>
            </div>
        </div>
    </div>
@endif
