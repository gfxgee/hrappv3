<?php

namespace App\Services;

use App\Models\Praise;
use App\Models\PraiseSession;
use App\Models\User;

/**
 * Ranks praise recipients for a cycle — used by the Praise Wall podium and
 * the HR Overview leaderboard.
 */
class PraisePodium
{
    /**
     * Rank the recipients of a cycle by total reactions received, then by
     * praise count. Returns the top entries for the podium / leaderboard.
     *
     * @return list<array{rank: int, user: ?User, reactions: int, praises: int}>
     */
    public function forSession(?PraiseSession $session, int $limit = 10): array
    {
        $rows = Praise::query()
            ->leftJoin('praise_reactions', 'praise_reactions.praise_id', '=', 'praises.id')
            ->when(
                $session !== null,
                fn ($query) => $query->where('praises.praise_session_id', $session->id),
                fn ($query) => $query->whereNull('praises.praise_session_id'),
            )
            ->groupBy('praises.recipient_id')
            ->selectRaw('praises.recipient_id, COUNT(DISTINCT praises.id) as praises, COUNT(praise_reactions.id) as reactions')
            ->orderByDesc('reactions')
            ->orderByDesc('praises')
            ->limit($limit)
            ->get();

        $users = User::query()
            ->whereIn('id', $rows->pluck('recipient_id'))
            ->get()
            ->keyBy('id');

        return $rows->values()->map(fn ($row, int $index): array => [
            'rank' => $index + 1,
            'user' => $users->get($row->recipient_id),
            'reactions' => (int) $row->reactions,
            'praises' => (int) $row->praises,
        ])->all();
    }
}
