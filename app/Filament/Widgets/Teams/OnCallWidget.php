<?php

namespace App\Filament\Widgets\Teams;

use App\Filament\Widgets\Employee\OnCallWidget as BaseOnCallWidget;
use Illuminate\Support\Str;

/**
 * On-call, drawn for the Teams tab.
 *
 * The same roster as the admin panel's card, with one difference that matters:
 * this is the only place the tab states who is on-call. The admin dashboard
 * says it twice — a banner at the top for whoever is on-call, and this card
 * for everyone — which collides for exactly the person it is addressing. Here
 * the card carries both jobs: most weeks it names a colleague to ring, and on
 * your own week it tints and says so.
 */
class OnCallWidget extends BaseOnCallWidget
{
    protected string $view = 'filament.widgets.teams.on-call-widget';

    /**
     * Initials for the roster avatar, so a colleague is recognisable before
     * their name is read. Two letters at most: more stops being an avatar.
     */
    public function initials(string $name): string
    {
        return Str::of($name)
            ->trim()
            ->explode(' ')
            ->filter()
            ->take(2)
            ->map(fn (string $part): string => Str::upper(Str::substr($part, 0, 1)))
            ->implode('');
    }
}
