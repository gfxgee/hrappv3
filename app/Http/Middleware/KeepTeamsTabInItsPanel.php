<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps someone browsing inside a Microsoft Teams tab in the Teams panel.
 *
 * Plenty of links point at /admin and always will: a stored notification, a
 * bookmark a colleague pasted into a chat, the `dashboard` route. Following
 * one from inside the tab would load the full admin panel in the iframe —
 * sidebar, logout button and all — which is exactly what the Teams panel
 * exists to avoid.
 *
 * Both panels register the same resources and pages, so their route names
 * differ only in the panel id and the equivalent can be resolved by name.
 * Anything with no counterpart (the backup manager, for one) is left alone
 * rather than redirected into a 404.
 */
class KeepTeamsTabInItsPanel
{
    private const ADMIN_PREFIX = 'filament.admin.';

    private const TEAMS_PREFIX = 'filament.teams-app.';

    public function handle(Request $request, Closure $next): Response
    {
        $target = $this->teamsPanelEquivalent($request);

        return $target === null ? $next($request) : redirect($target);
    }

    private function teamsPanelEquivalent(Request $request): ?string
    {
        if (! TeamsEmbed::isEmbedded($request)) {
            return null;
        }

        $route = $request->route();
        $name = $route?->getName();

        if ($name === null || ! str_starts_with($name, self::ADMIN_PREFIX)) {
            return null;
        }

        $equivalent = self::TEAMS_PREFIX.substr($name, strlen(self::ADMIN_PREFIX));

        if (! Route::has($equivalent)) {
            return null;
        }

        return route($equivalent, [
            ...$route->parameters(),
            ...$request->query(),
        ]);
    }
}
