<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;

/**
 * Makes the app usable inside a Microsoft Teams tab without loosening the
 * rules for everybody else.
 *
 * A Teams tab renders the app in a cross-site iframe, so the browser only
 * sends the session cookie when it is "SameSite=None; Secure". Applying that
 * globally would drop SameSite as a layer of CSRF defence for ordinary browser
 * logins too, so it is applied only to requests that are actually embedded.
 *
 * The two variants can coexist because the embedded one is also "Partitioned"
 * (CHIPS): the browser keeps a partitioned cookie in a jar keyed by the
 * embedding site, so the Teams session and a normal browser session never
 * overwrite each other's attributes.
 *
 * Also pins `frame-ancestors`, which nothing set before: Teams and its
 * companion hosts may embed the app, and nothing else may — so turning on
 * embedding does not leave the app open to clickjacking from any origin.
 */
class TeamsEmbed
{
    /**
     * Marks a browser context as living inside Teams. Needed because
     * Sec-Fetch-Dest is missing on some older mobile webviews; the entry page
     * always sets it, so later requests in the tab stay recognisable.
     *
     * Excluded from cookie encryption (see bootstrap/app.php) as this runs
     * before EncryptCookies and must read the value raw.
     */
    public const MARKER = 'teams_embed';

    /**
     * Hosts allowed to frame the app. Covers the Teams web and desktop
     * clients, Outlook and the Microsoft 365 app, which all render tabs.
     *
     * @var list<string>
     */
    private const TEAMS_FRAME_ANCESTORS = [
        'https://teams.microsoft.com',
        'https://*.teams.microsoft.com',
        'https://teams.cloud.microsoft',
        'https://*.cloud.microsoft',
        'https://*.skype.com',
        'https://outlook.office.com',
        'https://outlook.office365.com',
        'https://*.office.com',
        'https://*.microsoft365.com',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $embedded = $this->isEmbedded($request);

        if ($embedded) {
            // Must happen before StartSession, which reads these per request.
            config([
                'session.same_site' => 'none',
                'session.secure' => true,
                'session.partitioned' => true,
            ]);
        }

        $response = $next($request);

        $this->restrictFraming($response, $embedded);

        if ($embedded && ! $request->cookies->has(self::MARKER)) {
            $response->headers->setCookie($this->marker());
        }

        return $response;
    }

    /**
     * The tab entry point is only ever opened by Teams, so it seeds the
     * marker; from then on the marker (or Sec-Fetch-Dest on browsers that
     * send it) identifies the embedded context.
     */
    private function isEmbedded(Request $request): bool
    {
        if ($request->is('teams', 'teams/*')) {
            return true;
        }

        if ($request->cookies->has(self::MARKER)) {
            return true;
        }

        return in_array($request->headers->get('Sec-Fetch-Dest'), ['iframe', 'frame'], true)
            && $request->headers->get('Sec-Fetch-Site') === 'cross-site';
    }

    private function marker(): Cookie
    {
        return Cookie::create(self::MARKER, '1')
            ->withExpires(0)
            ->withPath('/')
            ->withSecure(true)
            ->withHttpOnly(true)
            ->withSameSite(Cookie::SAMESITE_NONE)
            ->withPartitioned(true);
    }

    /**
     * Allow only Teams to frame the app when embedded, and nothing at all
     * otherwise. Left alone if the app already sets its own policy.
     */
    private function restrictFraming(Response $response, bool $embedded): void
    {
        if ($response->headers->has('Content-Security-Policy')) {
            return;
        }

        $ancestors = $embedded
            ? "'self' ".implode(' ', self::TEAMS_FRAME_ANCESTORS)
            : "'self'";

        $response->headers->set('Content-Security-Policy', "frame-ancestors {$ancestors}");
    }
}
