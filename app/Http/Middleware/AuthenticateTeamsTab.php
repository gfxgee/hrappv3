<?php

namespace App\Http\Middleware;

use Filament\Http\Middleware\Authenticate;

/**
 * Authentication for the Microsoft Teams tab.
 *
 * The Teams panel deliberately has no login page: there is nobody to show one
 * to. When the session lapses — it expires, or the employee reopens the tab
 * days later — sending them to a login form inside the iframe is a dead end,
 * because the Microsoft popup is the only way to sign in there.
 *
 * So guests go back to the tab entry point instead, which re-establishes the
 * session from their Teams identity without anyone typing anything.
 */
class AuthenticateTeamsTab extends Authenticate
{
    protected function redirectTo($request): ?string
    {
        return route('teams.entry');
    }
}
