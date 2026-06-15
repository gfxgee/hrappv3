<?php

namespace App\Filament\Pages;

use App\Filament\Support\EnhanceReason;
use App\Models\Badge;
use App\Models\Praise;
use App\Models\PraiseComment;
use App\Models\PraiseReaction;
use App\Models\PraiseSession;
use App\Models\User;
use App\Services\GifSearch;
use App\Services\PraisePodium;
use App\Services\ReasonEnhancer;
use App\Settings\GeneralSettings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Throwable;

class PraiseWall extends Page
{
    protected string $view = 'filament.pages.praise-wall';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHeart;

    protected static ?string $title = 'Praise Wall';

    protected static string|\UnitEnum|null $navigationGroup = 'My Workspace';

    protected static ?int $navigationSort = 0;

    public const REACTION = '❤️';

    /** @var list<string> */
    protected const CYCLE_MANAGER_ROLES = ['superadmin', 'super_admin', 'hr'];

    /**
     * Default GIFs to fetch per page (used for infinite scroll), seeded into settings.
     */
    public const GIF_PER_PAGE = 12;

    /** Configured number of GIFs to fetch per page. */
    public function gifPerPage(): int
    {
        return app(GeneralSettings::class)->praiseGifPerPage;
    }

    /**
     * Active feed sort: recent | liked | commented | top_recipients.
     */
    public string $sort = 'recent';

    /**
     * Allowed feed sorts and their button labels.
     *
     * @var array<string, string>
     */
    public const SORTS = [
        'recent' => '📅 Most recent',
        'liked' => '💖 Most liked',
        'commented' => '💬 Most commented',
        'top_recipients' => '🏅 Top recipients',
    ];

    /**
     * Draft text for the comment composer in the open praise modal.
     */
    public string $newComment = '';

    /**
     * The comment currently being edited inline, if any.
     */
    public ?int $editingCommentId = null;

    /**
     * Draft text while editing an existing comment.
     */
    public string $editingCommentText = '';

    /**
     * Current GIF search query in the comment picker.
     */
    public string $gifQuery = '';

    /**
     * Results of the latest GIF search (accumulated across pages).
     *
     * @var list<array{id: string, preview: string, full: string}>
     */
    public array $gifResults = [];

    /**
     * Offset of the next GIF page to fetch.
     */
    public int $gifOffset = 0;

    /**
     * Whether more GIF results are likely available for the current query.
     */
    public bool $gifHasMore = false;

    /**
     * The GIF the user has picked to attach to their comment, if any.
     */
    public ?string $selectedGifUrl = null;

    /**
     * Praise count for the current cycle (or the uncategorized wall).
     */
    public static function getNavigationBadge(): ?string
    {
        $current = PraiseSession::current();

        $count = $current !== null
            ? Praise::where('praise_session_id', $current->id)->count()
            : Praise::whereNull('praise_session_id')->count();

        return (string) $count;
    }

    public function getSubheading(): ?string
    {
        $current = PraiseSession::current();

        return $current !== null
            ? "Current cycle: {$current->name}"
            : 'Everyday recognition';
    }

    public function canManageCycles(): bool
    {
        return (bool) auth()->user()?->hasAnyRole(self::CYCLE_MANAGER_ROLES);
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            $this->giveAction(),
            $this->finishCycleAction(),
        ];
    }

    public function giveAction(): Action
    {
        return Action::make('give')
            ->label('Give Praise')
            ->icon('heroicon-o-heart')
            ->modalHeading('Give a shout-out')
            ->modalSubmitActionLabel('Post Praise')
            ->schema([
                Select::make('recipient_id')
                    ->label('Who deserves a shout-out?')
                    ->options(fn (): array => User::active()
                        ->whereKeyNot(auth()->id())
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all())
                    ->searchable()
                    ->required(),
                Select::make('badge_id')
                    ->label('Badge')
                    ->options(fn (): array => Badge::active()
                        ->orderBy('label')
                        ->get()
                        ->mapWithKeys(fn (Badge $badge): array => [
                            $badge->id => trim(($badge->icon ? $badge->icon.' ' : '').$badge->label),
                        ])
                        ->all())
                    ->placeholder('No badge')
                    ->native(false),
                Textarea::make('message')
                    ->label('Why are they awesome?')
                    ->required()
                    ->maxLength(1000)
                    ->hintActions(EnhanceReason::for('praise', 'message')),
            ])
            ->action(function (array $data): void {
                if ((int) $data['recipient_id'] === (int) auth()->id()) {
                    Notification::make()
                        ->warning()
                        ->title('You can\'t praise yourself')
                        ->send();

                    return;
                }

                Praise::create([
                    'user_id' => auth()->id(),
                    'recipient_id' => $data['recipient_id'],
                    'badge_id' => $data['badge_id'] ?? null,
                    'praise_session_id' => PraiseSession::current()?->id,
                    'message' => $data['message'],
                ]);

                Notification::make()->success()->title('Praise posted 🎉')->send();

                // Celebrate the shout-out with a confetti burst on the wall.
                $this->dispatch('praise-confetti');
            });
    }

    public function finishCycleAction(): Action
    {
        return Action::make('finishCycle')
            ->label('Finish Cycle')
            ->icon('heroicon-o-trophy')
            ->color('gray')
            // Rain confetti the moment the button is clicked (before any submit).
            ->extraAttributes(['x-on:click' => 'window.fallingConfetti?.()'])
            ->visible(fn (): bool => $this->canManageCycles())
            ->modalHeading('Finish cycle & crown the winners')
            ->modalDescription('Review the podium below, then archive this cycle and start a fresh one.')
            ->modalContent(fn () => view('filament.praise.podium', [
                'podium' => $this->podiumForSession(PraiseSession::current()),
                'cycleName' => PraiseSession::current()?->name,
            ]))
            ->modalSubmitActionLabel('Archive & start new cycle')
            ->schema([
                TextInput::make('name')
                    ->label('New cycle name')
                    ->required()
                    ->maxLength(255)
                    ->default(now()->format('F Y')),
            ])
            ->action(function (array $data): void {
                PraiseSession::create([
                    'name' => $data['name'],
                    'is_active' => true,
                ]);

                Notification::make()
                    ->success()
                    ->title('New cycle started 🎉')
                    ->body('The previous cycle is archived with its winners.')
                    ->send();
            });
    }

    /**
     * Switch the feed sort (validated against the allowed set).
     */
    public function setSort(string $sort): void
    {
        $this->sort = array_key_exists($sort, self::SORTS) ? $sort : 'recent';
    }

    /**
     * Rank the recipients of a cycle by total reactions received, then by
     * praise count. Delegates to the shared PraisePodium service.
     *
     * @return list<array{rank: int, user: ?User, reactions: int, praises: int}>
     */
    public function podiumForSession(?PraiseSession $session, int $limit = 10): array
    {
        return app(PraisePodium::class)->forSession($session, $limit);
    }

    /**
     * The praise feed for the current cycle. When no cycle is active, shows
     * praises not tied to any cycle (the pre-cycle "everyday" wall).
     *
     * @return Collection<int, Praise>
     */
    public function getPraises(): Collection
    {
        $current = PraiseSession::current();

        $praises = Praise::query()
            ->with(['sender', 'recipient', 'badge', 'reactions', 'comments.user'])
            ->withCount(['reactions', 'comments'])
            ->when(
                $current !== null,
                fn ($query) => $query->where('praise_session_id', $current->id),
                fn ($query) => $query->whereNull('praise_session_id'),
            )
            ->latest()
            ->limit(100)
            ->get();

        return match ($this->sort) {
            'liked' => $praises->sortByDesc('reactions_count')->values(),
            'commented' => $praises->sortByDesc('comments_count')->values(),
            'top_recipients' => $this->sortByTopRecipients($praises),
            default => $praises,
        };
    }

    /**
     * Cluster the feed by recipient, ordering recipients by the total reactions
     * they received in the cycle (matching the podium ranking).
     *
     * @param  Collection<int, Praise>  $praises
     * @return Collection<int, Praise>
     */
    protected function sortByTopRecipients(Collection $praises): Collection
    {
        $reactionsByRecipient = $praises
            ->groupBy('recipient_id')
            ->map(fn (Collection $group): int => $group->sum('reactions_count'));

        return $praises
            ->sortByDesc(fn (Praise $praise): int => ($reactionsByRecipient[$praise->recipient_id] ?? 0) * 1_000_000
                + $praise->reactions_count)
            ->values();
    }

    public function toggleReaction(int $praiseId): void
    {
        $reaction = PraiseReaction::query()
            ->where('praise_id', $praiseId)
            ->where('user_id', auth()->id())
            ->where('type', self::REACTION)
            ->first();

        if ($reaction !== null) {
            $reaction->delete();

            return;
        }

        PraiseReaction::create([
            'praise_id' => $praiseId,
            'user_id' => auth()->id(),
            'type' => self::REACTION,
        ]);
    }

    public function hasReacted(Praise $praise): bool
    {
        return $praise->reactions
            ->where('user_id', auth()->id())
            ->where('type', self::REACTION)
            ->isNotEmpty();
    }

    /**
     * Load a single praise with everything the detail modal needs.
     */
    public function loadPraise(int $id): ?Praise
    {
        return Praise::query()
            ->with(['sender', 'recipient', 'badge', 'reactions', 'comments.user'])
            ->find($id);
    }

    /**
     * Live-search GIFs as the query in the comment picker changes.
     */
    public function updatedGifQuery(): void
    {
        $this->searchGifs();
    }

    /**
     * Run a fresh GIF search for the current query (min. 2 characters),
     * replacing any previous results.
     */
    public function searchGifs(): void
    {
        $this->gifOffset = 0;
        $this->gifResults = [];
        $this->gifHasMore = false;

        if (mb_strlen(trim($this->gifQuery)) < 2) {
            return;
        }

        $page = app(GifSearch::class)->search($this->gifQuery, $this->gifPerPage(), 0);

        $this->gifResults = $page;
        $this->gifOffset = count($page);
        $this->gifHasMore = count($page) === $this->gifPerPage();
    }

    /**
     * Fetch and append the next page of GIF results (infinite scroll).
     */
    public function loadMoreGifs(): void
    {
        if (! $this->gifHasMore || mb_strlen(trim($this->gifQuery)) < 2) {
            return;
        }

        $page = app(GifSearch::class)->search($this->gifQuery, $this->gifPerPage(), $this->gifOffset);

        // Skip IDs already shown, in case the provider overlaps pages.
        $existingIds = collect($this->gifResults)->pluck('id')->all();
        $fresh = array_values(array_filter(
            $page,
            fn (array $gif): bool => ! in_array($gif['id'], $existingIds, true),
        ));

        $this->gifResults = array_merge($this->gifResults, $fresh);
        $this->gifOffset += count($page);
        $this->gifHasMore = count($page) === $this->gifPerPage();
    }

    /**
     * Attach a GIF to the comment. Only URLs from the current results may be
     * selected, so an arbitrary URL can't be injected from the client.
     */
    public function selectGif(string $url): void
    {
        if (collect($this->gifResults)->contains('full', $url)) {
            $this->selectedGifUrl = $url;
        }
    }

    public function clearSelectedGif(): void
    {
        $this->selectedGifUrl = null;
    }

    /**
     * Reset the composer (draft text, GIF picker, edit state) for a fresh modal.
     */
    public function resetComposer(): void
    {
        $this->newComment = '';
        $this->gifQuery = '';
        $this->gifResults = [];
        $this->gifOffset = 0;
        $this->gifHasMore = false;
        $this->selectedGifUrl = null;
        $this->cancelEditComment();
    }

    /**
     * AI-polish or expand the comment draft, in place.
     *
     * @param  'polish'|'expand'  $mode
     */
    public function enhanceNewComment(string $mode): void
    {
        $draft = trim($this->newComment);

        if ($draft === '') {
            Notification::make()->warning()->title('Write a draft first')->send();

            return;
        }

        try {
            $this->newComment = app(ReasonEnhancer::class)->enhance($draft, $mode, ['kind' => 'comment']);
        } catch (Throwable $e) {
            report($e);
            Notification::make()->danger()->title('AI enhancement failed')->send();
        }
    }

    /**
     * Whether the AI reason enhancer is configured (controls the Polish/Expand buttons).
     */
    public function canEnhanceComment(): bool
    {
        return app(ReasonEnhancer::class)->isConfigured();
    }

    /**
     * Post a new comment on the praise. The modal stays open afterwards.
     */
    public function postComment(int $praiseId): void
    {
        $comment = trim($this->newComment);

        if ($comment === '' && blank($this->selectedGifUrl)) {
            Notification::make()->warning()->title('Add a comment or pick a GIF')->send();

            return;
        }

        PraiseComment::create([
            'praise_id' => $praiseId,
            'user_id' => auth()->id(),
            'comment' => $comment !== '' ? $comment : null,
            'gif_url' => $this->selectedGifUrl,
        ]);

        $this->resetComposer();

        Notification::make()->success()->title('Comment added')->send();
    }

    /**
     * Begin editing one of the current user's own comments.
     */
    public function editComment(int $commentId): void
    {
        $comment = PraiseComment::find($commentId);

        if ($comment === null || ! $this->ownsComment($comment)) {
            return;
        }

        $this->editingCommentId = $comment->id;
        $this->editingCommentText = (string) $comment->comment;
    }

    public function cancelEditComment(): void
    {
        $this->editingCommentId = null;
        $this->editingCommentText = '';
    }

    /**
     * Save the edited text for the user's own comment.
     */
    public function updateComment(int $commentId): void
    {
        $comment = PraiseComment::find($commentId);

        if ($comment === null || ! $this->ownsComment($comment)) {
            return;
        }

        $text = trim($this->editingCommentText);

        if ($text === '' && blank($comment->gif_url)) {
            Notification::make()->warning()->title('Comment can\'t be empty')->send();

            return;
        }

        $comment->update(['comment' => $text !== '' ? $text : null]);

        $this->cancelEditComment();

        Notification::make()->success()->title('Comment updated')->send();
    }

    /**
     * Delete one of the current user's own comments.
     */
    public function deleteComment(int $commentId): void
    {
        $comment = PraiseComment::find($commentId);

        if ($comment === null || ! $this->ownsComment($comment)) {
            return;
        }

        $comment->delete();

        Notification::make()->success()->title('Comment deleted')->send();
    }

    /**
     * Whether the authenticated user owns the given comment.
     */
    public function ownsComment(PraiseComment $comment): bool
    {
        return (int) $comment->user_id === (int) auth()->id();
    }

    /**
     * Whether the authenticated user sent (nominated) the given praise.
     */
    public function ownsPraise(Praise $praise): bool
    {
        return (int) $praise->user_id === (int) auth()->id();
    }

    /**
     * Edit a praise you sent — change its badge or message.
     */
    public function editPraiseAction(): Action
    {
        return Action::make('editPraise')
            ->modalHeading('Edit praise')
            ->modalSubmitActionLabel('Save changes')
            ->fillForm(fn (array $arguments): array => [
                'badge_id' => $this->loadPraise((int) $arguments['praise'])?->badge_id,
                'message' => $this->loadPraise((int) $arguments['praise'])?->message,
            ])
            ->schema([
                Select::make('badge_id')
                    ->label('Badge')
                    ->options(fn (): array => Badge::active()
                        ->orderBy('label')
                        ->get()
                        ->mapWithKeys(fn (Badge $badge): array => [
                            $badge->id => trim(($badge->icon ? $badge->icon.' ' : '').$badge->label),
                        ])
                        ->all())
                    ->placeholder('No badge')
                    ->native(false),
                Textarea::make('message')
                    ->label('Why are they awesome?')
                    ->required()
                    ->maxLength(1000)
                    ->hintActions(EnhanceReason::for('praise', 'message')),
            ])
            ->action(function (array $arguments, array $data): void {
                $praise = Praise::find((int) $arguments['praise']);

                if ($praise === null || ! $this->ownsPraise($praise)) {
                    Notification::make()->danger()->title('You can only edit your own praise')->send();

                    return;
                }

                $praise->update([
                    'badge_id' => $data['badge_id'] ?? null,
                    'message' => $data['message'],
                ]);

                Notification::make()->success()->title('Praise updated')->send();
            });
    }

    /**
     * Delete a praise you sent, along with its reactions and comments.
     */
    public function deletePraiseAction(): Action
    {
        return Action::make('deletePraise')
            ->requiresConfirmation()
            ->color('danger')
            ->modalHeading('Delete this praise?')
            ->modalDescription('This permanently removes the praise along with its reactions and comments.')
            ->modalSubmitActionLabel('Delete')
            ->action(function (array $arguments): void {
                $praise = Praise::find((int) $arguments['praise']);

                if ($praise === null || ! $this->ownsPraise($praise)) {
                    Notification::make()->danger()->title('You can only delete your own praise')->send();

                    return;
                }

                $praise->delete();

                Notification::make()->success()->title('Praise deleted')->send();
            });
    }

    /**
     * The "open a praise" detail modal — shows the post, the heart, all
     * comments, and an inline composer. The modal stays open after posting,
     * editing, or deleting comments (Facebook/Slack style).
     */
    public function viewPraiseAction(): Action
    {
        return Action::make('viewPraise')
            ->modalHeading('🎉 Praise')
            ->modalContent(fn (array $arguments) => view('filament.praise.detail', [
                'praise' => $this->loadPraise((int) $arguments['praise']),
            ]))
            ->modalWidth('lg')
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Close')
            // Reset the composer and rain confetti every time the modal opens.
            ->mountUsing(function (): void {
                $this->resetComposer();
                $this->dispatch('praise-opened');
            });
    }
}
