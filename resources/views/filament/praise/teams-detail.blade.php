{{--
    An opened praise, drawn for the Microsoft Teams tab.

    Same component, same wire bindings and the same comment rules as
    /admin's detail view — this is the certificate's inside, styled to match
    the wall it came from.

    Deliberate differences from /admin: the badge is the amber pill it is on
    the card, the reaction is a filled pill once it is yours rather than a
    change of text colour, and each comment is a card on the soft ground
    instead of a grey strip, so a GIF in one has something to sit on.
--}}
@php
    use App\Filament\Pages\PraiseWall;
@endphp

@if ($praise)
    @php
        $recipient = $praise->recipient;
        $avatar = $recipient?->getFilamentAvatarUrl()
            ?? 'https://ui-avatars.com/api/?name='.urlencode($recipient?->name ?? 'User').'&size=160';
        $reactions = $praise->reactions->where('type', PraiseWall::REACTION)->count();
        $commentCount = $praise->comments->count();
        $reacted = $this->hasReacted($praise);
    @endphp

    <div>
        {{-- Who it is for, and who sent it --}}
        <div class="flex items-center gap-[13px]">
            <img
                src="{{ $avatar }}"
                alt="{{ $recipient?->name }}"
                class="size-[52px] shrink-0 rounded-full object-cover"
            />

            <div class="min-w-0">
                <p class="text-[17px] font-bold text-purple-950 dark:text-white">
                    {{ $recipient?->name ?? 'Unknown' }}
                </p>
                <p class="mt-0.5 text-[12.5px]" style="color: var(--hr-ink-soft)">
                    Submitted by {{ $praise->senderName() }}, {{ $praise->created_at?->diffForHumans() }}
                </p>
            </div>
        </div>

        @if ($praise->badge)
            <div class="mt-3.5">
                <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-400/20 px-3.5 py-[5px] text-[13px] font-bold text-amber-900 dark:text-amber-200">
                    <span aria-hidden="true">{{ $praise->badge->icon ?: '🏅' }}</span>{{ $praise->badge->label }}
                </span>
            </div>
        @endif

        <p class="mt-3.5 text-[15px] leading-relaxed whitespace-pre-line text-purple-950 dark:text-white">
            {{ $praise->message }}
        </p>

        {{-- Reactions --}}
        <div class="mt-4 flex items-center gap-3.5 border-y py-2.5" style="border-color: var(--hr-ground)">
            <button
                type="button"
                wire:click="toggleReaction({{ $praise->id }})"
                aria-pressed="{{ $reacted ? 'true' : 'false' }}"
                @class([
                    'inline-flex h-9 items-center gap-[7px] rounded-[11px] px-[13px] text-sm font-bold transition',
                    'bg-amber-400/20 text-amber-900 dark:text-amber-200' => $reacted,
                    'hover:bg-[color:var(--hr-ground)]' => ! $reacted,
                ])
                @if (! $reacted) style="color: var(--hr-ink-soft)" @endif
            >
                <span class="text-[15px]" aria-hidden="true">{{ PraiseWall::REACTION }}</span>{{ $reactions }}
            </button>

            <span class="text-[13.5px]" style="color: var(--hr-ink-soft)">
                {{ $commentCount }} {{ Str::plural('comment', $commentCount) }}
            </span>
        </div>

        {{-- Comments --}}
        @if ($praise->comments->isEmpty())
            <p class="mt-3.5 text-[13.5px]" style="color: var(--hr-ink-soft)">No comments yet — be the first.</p>
        @else
            <div class="max-h-[320px] overflow-y-auto pr-1">
                @foreach ($praise->comments as $comment)
                    <div
                        wire:key="comment-{{ $comment->id }}"
                        class="group mt-[11px] rounded-2xl px-3.5 py-3"
                        style="background: var(--hr-ground-soft)"
                    >
                        <div class="flex items-baseline gap-2.5">
                            <span class="min-w-0 flex-1 truncate text-[13.5px] font-bold text-purple-950 dark:text-white">
                                {{ $comment->user?->name ?? 'Unknown' }}
                            </span>

                            @if ($this->ownsComment($comment) && $this->editingCommentId !== $comment->id)
                                <span class="flex shrink-0 gap-2.5 opacity-0 transition group-hover:opacity-100 focus-within:opacity-100">
                                    <button
                                        type="button"
                                        wire:click="editComment({{ $comment->id }})"
                                        class="text-[12.5px] font-bold hover:underline"
                                        style="color: var(--hr-ink-soft)"
                                    >Edit</button>

                                    {{-- Filament modal, not wire:confirm: native
                                         dialogs are ignored in the Teams tab. --}}
                                    <button
                                        type="button"
                                        wire:click="mountAction('confirmDeleteComment', { comment: {{ $comment->id }} })"
                                        class="text-[12.5px] font-bold hover:underline"
                                        style="color: var(--hr-ink-soft)"
                                    >Delete</button>
                                </span>
                            @endif
                        </div>

                        @if ($this->editingCommentId === $comment->id)
                            <div class="mt-2 space-y-2">
                                <textarea
                                    wire:model="editingCommentText"
                                    rows="2"
                                    class="w-full rounded-2xl border-0 px-3.5 py-2.5 text-[13.5px] leading-relaxed"
                                    style="background: var(--hr-card); box-shadow: inset 0 0 0 1.5px var(--hr-field-line); color: var(--hr-ink)"
                                ></textarea>

                                <div class="flex items-center gap-2.5">
                                    <button type="button" wire:click="updateComment({{ $comment->id }})" class="hr-cta h-9 px-4 text-[13.5px]">Save</button>
                                    <button
                                        type="button"
                                        wire:click="cancelEditComment"
                                        class="text-[12.5px] font-bold hover:underline"
                                        style="color: var(--hr-ink-soft)"
                                    >Cancel</button>
                                </div>
                            </div>
                        @else
                            @if (filled($comment->comment))
                                <p class="mt-1 text-[13.5px] leading-relaxed text-purple-950 dark:text-white">{{ $comment->comment }}</p>
                            @endif

                            @if ($comment->gif_url)
                                <img src="{{ $comment->gif_url }}" alt="GIF" loading="lazy" class="mt-2 max-h-40 rounded-xl object-contain" />
                            @endif

                            <p class="mt-1.5 text-[12px]" style="color: var(--hr-ink-soft)">{{ $comment->created_at?->diffForHumans() }}</p>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif

        {{-- Composer. Posting leaves the modal open. --}}
        <div class="mt-4 border-t pt-4" style="border-color: var(--hr-ground)">
            <div class="mb-[7px] flex items-center justify-between gap-3">
                <label for="hr-pd-comment" class="text-[12.5px] font-bold" style="color: var(--hr-ink-soft)">Write a comment</label>

                @if ($this->canEnhanceComment())
                    <div class="flex gap-1.5">
                        @foreach ([['polish', 'Polish', 'heroicon-m-sparkles'], ['expand', 'Expand', 'heroicon-m-arrows-pointing-out']] as [$mode, $label, $icon])
                            <button
                                type="button"
                                wire:click="enhanceNewComment('{{ $mode }}')"
                                wire:loading.attr="disabled"
                                wire:target="enhanceNewComment"
                                class="inline-flex items-center gap-1.5 rounded-full px-[11px] py-[5px] text-[12.5px] font-bold text-primary-600 transition dark:text-primary-400"
                                style="box-shadow: inset 0 0 0 1.5px var(--hr-field-line)"
                            >
                                <x-filament::icon :icon="$icon" class="size-3.5" />{{ $label }}
                            </button>
                        @endforeach
                    </div>
                @endif
            </div>

            <textarea
                id="hr-pd-comment"
                wire:model="newComment"
                rows="2"
                maxlength="1000"
                placeholder="Add a comment"
                class="w-full rounded-2xl border-0 px-3.5 py-3 text-sm leading-relaxed"
                style="background: var(--hr-ground-soft); box-shadow: inset 0 0 0 1.5px var(--hr-field-line); color: var(--hr-ink)"
            ></textarea>

            <div class="mt-[11px] flex items-end gap-2.5">
                @include('filament.praise.gif-picker', [
                    'triggerClass' => 'hr-btn h-[42px] gap-[7px] px-3.5 text-[13.5px]',
                    'triggerLabel' => 'Add a GIF',
                ])

                <span class="flex-1"></span>

                <button
                    type="button"
                    wire:click="postComment({{ $praise->id }})"
                    wire:loading.attr="disabled"
                    wire:target="postComment"
                    class="hr-cta h-[42px] shrink-0 px-5 text-[14.5px] disabled:opacity-60"
                >
                    <x-filament::loading-indicator wire:loading wire:target="postComment" class="size-4" />
                    Post comment
                </button>
            </div>
        </div>
    </div>
@endif
