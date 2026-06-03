<?php

namespace App\Filament\Pages;

use App\Filament\Support\EnhanceReason;
use App\Models\Badge;
use App\Models\Praise;
use App\Models\PraiseComment;
use App\Models\PraiseReaction;
use App\Models\PraiseSession;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;

class PraiseWall extends Page
{
    protected string $view = 'filament.pages.praise-wall';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHeart;

    protected static ?string $title = 'Praise Wall';

    protected static ?int $navigationSort = -1;

    public const REACTION = '❤️';

    /** @var list<string> */
    protected const CYCLE_MANAGER_ROLES = ['superadmin', 'super_admin', 'hr'];

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
            $this->startNewCycleAction(),
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
            });
    }

    public function startNewCycleAction(): Action
    {
        return Action::make('startNewCycle')
            ->label('Start New Cycle')
            ->icon('heroicon-o-arrow-path')
            ->color('gray')
            ->visible(fn (): bool => $this->canManageCycles())
            ->modalHeading('Start a new recognition cycle')
            ->modalDescription('Praises on the wall now will be archived to the current cycle, and the wall starts fresh.')
            ->schema([
                TextInput::make('name')
                    ->label('Cycle name')
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
                    ->body('The wall is fresh — previous praises are archived.')
                    ->send();
            });
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

        return Praise::query()
            ->with(['sender', 'recipient', 'badge', 'reactions', 'comments.user'])
            ->when(
                $current !== null,
                fn ($query) => $query->where('praise_session_id', $current->id),
                fn ($query) => $query->whereNull('praise_session_id'),
            )
            ->latest()
            ->limit(60)
            ->get();
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
     * The "open a praise" detail modal — shows the post, the heart, all
     * comments, and a box to add one (Facebook-post style).
     */
    public function viewPraiseAction(): Action
    {
        return Action::make('viewPraise')
            ->modalHeading(fn (array $arguments): string => $this->loadPraise((int) $arguments['praise'])?->recipient?->name ?? 'Praise')
            ->modalContent(fn (array $arguments) => view('filament.praise.detail', [
                'praise' => $this->loadPraise((int) $arguments['praise']),
            ]))
            ->modalSubmitActionLabel('Post Comment')
            ->modalWidth('lg')
            ->schema([
                Textarea::make('comment')
                    ->label('Write a comment')
                    ->required()
                    ->maxLength(1000)
                    ->hintActions(EnhanceReason::for('comment', 'comment')),
            ])
            ->action(function (array $arguments, array $data): void {
                PraiseComment::create([
                    'praise_id' => $arguments['praise'],
                    'user_id' => auth()->id(),
                    'comment' => $data['comment'],
                ]);

                Notification::make()->success()->title('Comment added')->send();
            });
    }
}
