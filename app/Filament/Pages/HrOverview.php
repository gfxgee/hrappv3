<?php

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

/**
 * Org-wide HR dashboard: headcount, pending approvals, absence, overtime,
 * leave trends, praise leaderboard, and the week ahead. Manager roles only —
 * the personal employee dashboard remains the panel home for everyone.
 */
class HrOverview extends Page
{
    protected string $view = 'filament.pages.hr-overview';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPresentationChartBar;

    protected static ?string $navigationLabel = 'HR Overview';

    protected static ?string $title = 'HR Overview';

    protected static ?int $navigationSort = -2;

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->isManager();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function getSubheading(): ?string
    {
        return now()->format('l, j F Y');
    }
}
