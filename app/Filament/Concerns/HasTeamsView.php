<?php

namespace App\Filament\Concerns;

use Filament\Facades\Filament;
use Illuminate\Contracts\Support\Htmlable;

/**
 * One page object, skinned for whichever panel asked for it.
 *
 * The page keeps a single set of methods, so /admin and the Microsoft Teams
 * tab cannot drift apart on what the figures mean — only on how they look.
 * Bringing the Teams look to /admin later is `teamsView()` becoming the view
 * everyone gets.
 */
trait HasTeamsView
{
    /**
     * The view this page uses inside the Teams tab.
     */
    abstract protected function teamsView(): string;

    public function getView(): string
    {
        return $this->inTeamsTab() ? $this->teamsView() : $this->view;
    }

    /**
     * The Teams views open with a band carrying the page's own title, so
     * Filament's heading above it would be a stutter. Filament skips the
     * whole header block when the heading is blank.
     */
    public function getHeading(): string|Htmlable|null
    {
        return $this->inTeamsTab() ? '' : parent::getHeading();
    }

    /**
     * Every Teams view opens with a band and draws the notice under it, so
     * the panel's PAGE_START hook must stand down for these pages.
     *
     * This is asked of the page rather than worked out from the route,
     * because a Livewire update is a POST to Livewire's own endpoint: any
     * route check passes on first load and fails on every re-render, which
     * is how a second notice appears the moment you open a modal.
     */
    public function drawsOwnAnnouncements(): bool
    {
        return $this->inTeamsTab();
    }

    protected function inTeamsTab(): bool
    {
        return Filament::getCurrentOrDefaultPanel()?->getId() === 'teams-app';
    }
}
