<?php

namespace App\Filament\Widgets\Teams;

use App\Enum\AttendanceStatus;
use App\Enum\LeaveType;
use App\Models\OverTimeRequest;
use App\Models\User;
use App\Services\LeaveCreditService;
use Filament\Facades\Filament;
use Filament\Widgets\Widget;
use Illuminate\Support\Str;

/**
 * The three figures at the top of the Teams tab: leave left, sick days and
 * overtime this month.
 *
 * The same numbers as the admin panel's stat cards, drawn differently. Each
 * carries a bar for the proportion its note states, so "12 of 15 days" can be
 * read at a glance rather than divided in your head, and each has its own
 * accent so the three read as a set rather than three copies of one card.
 *
 * Teams only. It is registered for both panels because widget discovery is
 * shared, but canView() keeps it where it was designed to be.
 */
class LeaveBalanceStatsWidget extends Widget
{
    protected string $view = 'filament.widgets.teams.leave-balance-stats-widget';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = -4;

    public static function canView(): bool
    {
        return auth()->check()
            && Filament::getCurrentOrDefaultPanel()?->getId() === 'teams-app';
    }

    /**
     * @return array<int, array{
     *     glyph: string,
     *     label: string,
     *     value: string,
     *     count: float,
     *     places: int,
     *     unit: string,
     *     note: string,
     *     tile: string,
     *     accent: string,
     *     fill: float,
     *     accent2: ?string,
     *     fill2: float,
     * }>
     */
    public function stats(): array
    {
        /** @var User $user */
        $user = auth()->user();

        return array_values(array_filter([
            $this->leaveStat($user, LeaveType::VACATION, '🌴', 'Leave left', 'rgb(249 159 41 / 0.18)', '#f99f29'),
            $this->leaveStat($user, LeaveType::SICK, '🤒', 'Sick days', 'rgb(0 159 227 / 0.14)', '#009fe3'),
            $this->overtimeStat($user),
        ]));
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function leaveStat(User $user, LeaveType $type, string $glyph, string $label, string $tile, string $accent): ?array
    {
        $credits = app(LeaveCreditService::class);
        $total = $credits->totalCredit($user, $type);

        // Nothing honest to draw a bar against when the type is untracked.
        if ($total === null || $total <= 0.0) {
            return null;
        }

        $remaining = max(0.0, (float) $credits->remainingDays($user, $type));
        $used = (float) $credits->usedDays($user, $type, year: now()->year);

        return [
            'glyph' => $glyph,
            'label' => $label,
            'value' => $this->trim($remaining),
            'count' => $remaining,
            'places' => $this->places($remaining),
            'unit' => 'of '.$this->trim($total).' '.Str::plural('day', $total),
            'note' => $used > 0
                ? $this->trim($used).' '.Str::plural('day', $used).' used this year'
                : 'None used this year',
            'tile' => $tile,
            'accent' => $accent,
            'fill' => min(100.0, $remaining / $total * 100),
            'accent2' => null,
            'fill2' => 0.0,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function overtimeStat(User $user): array
    {
        $month = [now()->startOfMonth(), now()->endOfMonth()];

        $approved = (float) OverTimeRequest::query()
            ->where('user_id', $user->id)
            ->whereIn('status', [AttendanceStatus::APPROVED->value, AttendanceStatus::APPROVED_AND_VERIFIED->value])
            ->whereBetween('request_date', $month)
            ->sum('hours');

        $pending = (float) OverTimeRequest::query()
            ->where('user_id', $user->id)
            ->where('status', AttendanceStatus::FOR_APPROVAL->value)
            ->whereBetween('request_date', $month)
            ->sum('hours');

        $filed = $approved + $pending;

        return [
            'glyph' => '⏱️',
            'label' => 'Overtime this month',
            'value' => $this->trim($approved),
            'count' => $approved,
            'places' => $this->places($approved),
            'unit' => 'hours approved',
            'note' => $pending > 0
                ? $this->trim($pending).' more awaiting approval'
                : 'Nothing awaiting approval',
            'tile' => 'rgb(35 139 127 / 0.14)',
            'accent' => '#238b7f',
            // The split is the point of the second segment: approved against
            // what is still with an approver. With nothing filed there is no
            // ratio to show, so the bar stays empty rather than inventing one.
            'fill' => $filed > 0 ? $approved / $filed * 100 : 0.0,
            'accent2' => $pending > 0 ? '#f99f29' : null,
            'fill2' => $filed > 0 ? $pending / $filed * 100 : 0.0,
        ];
    }

    /** Drops a trailing ".0" so whole days read as "12", not "12.0". */
    protected function trim(float $value): string
    {
        return rtrim(rtrim(number_format($value, 1), '0'), '.');
    }

    protected function places(float $value): int
    {
        return fmod($value, 1.0) === 0.0 ? 0 : 1;
    }
}
