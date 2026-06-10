<?php

namespace App\Filament\Widgets\Hr;

use App\Models\PraiseSession;
use App\Models\User;
use App\Services\PraisePodium;
use Filament\Widgets\Widget;

/**
 * Top praise recipients for the current cycle, ranked by reactions.
 */
class PraiseLeaderboardWidget extends Widget
{
    protected string $view = 'filament.widgets.hr.praise-leaderboard-widget';

    protected int|string|array $columnSpan = ['default' => 1, 'md' => 1];

    protected static ?int $sort = 0;

    public static function canView(): bool
    {
        return (bool) auth()->user()?->isManager();
    }

    public function cycleName(): string
    {
        return PraiseSession::current()?->name ?? 'Everyday recognition';
    }

    /**
     * @return list<array{rank: int, user: ?User, reactions: int, praises: int}>
     */
    public function leaders(): array
    {
        return app(PraisePodium::class)->forSession(PraiseSession::current(), 5);
    }
}
