<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard as BaseDashboard;

/**
 * Two-column dashboard: a 2/3 left column (Time Tracking → On Leave Today)
 * and a 1/3 right column (Upcoming Birthdays → Holidays → Leaves), each
 * stacking independently. The widgets are embedded directly in the view so
 * the two columns can grow to different heights.
 */
class Dashboard extends BaseDashboard
{
    protected string $view = 'filament.pages.dashboard';
}
