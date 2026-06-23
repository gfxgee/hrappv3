<?php

namespace App\Filament\Widgets\Hr;

use App\Enum\Mood;
use App\Models\MoodCheckIn;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Collection;

/**
 * Today's team mood at a glance for the HR Overview: participation, the share
 * feeling good, and how many may need support.
 */
class MoodTodayWidget extends StatsOverviewWidget
{
    protected static ?int $sort = -3;

    protected int|string|array $columnSpan = 'full';

    protected ?string $heading = 'Team mood today';

    public static function canView(): bool
    {
        return (bool) auth()->user()?->isManager();
    }

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        /** @var Collection<int, Mood> $moods */
        $moods = MoodCheckIn::query()->forToday()->pluck('mood');

        $checkedIn = $moods->count();
        $staff = User::query()->active()->count();
        $attention = $moods->filter(fn (Mood $mood): bool => $mood->needsAttention())->count();
        $good = $checkedIn - $attention;

        return [
            $this->checkedInStat($checkedIn, $staff),
            $this->feelingGoodStat($good, $checkedIn),
            $this->needsAttentionStat($attention, $moods),
        ];
    }

    protected function checkedInStat(int $checkedIn, int $staff): Stat
    {
        $percent = $staff > 0 ? (int) round($checkedIn / $staff * 100) : 0;

        return Stat::make('🙂 Checked in today', (string) $checkedIn)
            ->description("{$percent}% of {$staff} employees")
            ->color($checkedIn > 0 ? 'success' : 'gray');
    }

    protected function feelingGoodStat(int $good, int $checkedIn): Stat
    {
        $percent = $checkedIn > 0 ? (int) round($good / $checkedIn * 100) : 0;

        return Stat::make('😀 Feeling good', (string) $good)
            ->description($checkedIn > 0 ? "{$percent}% of check-ins" : 'No check-ins yet')
            ->color('success');
    }

    /**
     * @param  Collection<int, Mood>  $moods
     */
    protected function needsAttentionStat(int $attention, Collection $moods): Stat
    {
        $breakdown = collect([Mood::STRESSED, Mood::TIRED, Mood::SICK])
            ->map(fn (Mood $mood): string => $moods->filter(fn (Mood $m): bool => $m === $mood)->count()
                .' '.$mood->emoji())
            ->implode('  ');

        return Stat::make('⚠️ Needs attention', (string) $attention)
            ->description($attention > 0 ? $breakdown : 'All good today')
            ->color($attention > 0 ? 'danger' : 'success');
    }
}
