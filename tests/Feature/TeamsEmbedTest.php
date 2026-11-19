<?php

use App\Http\Middleware\TeamsEmbed;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * The Teams tab renders the app in a cross-site iframe, which the browser
 * only sends cookies into when they are "SameSite=None; Secure". Applying
 * that to everyone would weaken ordinary browser sessions, so these tests pin
 * down that it happens for embedded requests and nowhere else.
 */
beforeEach(function () {
    // The array session driver never writes a cookie, so there would be
    // nothing to assert against.
    config(['session.driver' => 'file']);
});

/**
 * Symfony's Cookie keeps its name private, so it has to be read via getName().
 */
function cookieFrom(TestResponse $response, string $name): ?Cookie
{
    return collect($response->headers->getCookies())
        ->first(fn (Cookie $cookie): bool => $cookie->getName() === $name);
}

function sessionCookieFrom(TestResponse $response): ?Cookie
{
    return cookieFrom($response, (string) config('session.cookie'));
}

it('leaves the session cookie alone for an ordinary browser visit', function () {
    $cookie = sessionCookieFrom($this->withoutVite()->get('/'));

    expect($cookie)->not->toBeNull()
        ->and($cookie->getSameSite())->toBe('lax')
        ->and($cookie->isPartitioned())->toBeFalse();
});

it('relaxes the session cookie for the Teams tab', function () {
    $response = $this->withoutVite()->get('/teams');
    $cookie = sessionCookieFrom($response);

    expect($cookie)->not->toBeNull()
        ->and($cookie->getSameSite())->toBe('none')
        ->and($cookie->isSecure())->toBeTrue()
        // Partitioned keeps this jar separate from the normal browser one, so
        // the two sets of attributes cannot overwrite each other.
        ->and($cookie->isPartitioned())->toBeTrue();
});

it('marks the browser as embedded so later requests are recognised', function () {
    $marker = cookieFrom($this->withoutVite()->get('/teams'), TeamsEmbed::MARKER);

    expect($marker)->not->toBeNull()
        ->and($marker->getSameSite())->toBe('none')
        ->and($marker->isPartitioned())->toBeTrue();
});

it('treats a request carrying the marker as embedded', function () {
    $cookie = sessionCookieFrom(
        $this->withoutVite()
            ->withUnencryptedCookie(TeamsEmbed::MARKER, '1')
            ->get('/'),
    );

    expect($cookie->getSameSite())->toBe('none');
});

it('treats a cross-site iframe request as embedded', function () {
    $cookie = sessionCookieFrom(
        $this->withoutVite()->get('/', [
            'Sec-Fetch-Dest' => 'iframe',
            'Sec-Fetch-Site' => 'cross-site',
        ]),
    );

    expect($cookie->getSameSite())->toBe('none');
});

it('does not treat a same-site iframe as a Teams embed', function () {
    $cookie = sessionCookieFrom(
        $this->withoutVite()->get('/', [
            'Sec-Fetch-Dest' => 'iframe',
            'Sec-Fetch-Site' => 'same-origin',
        ]),
    );

    expect($cookie->getSameSite())->toBe('lax');
});

it('keeps the embedded session cookie on the Filament panel', function () {
    // The panel lists its middleware explicitly rather than using the web
    // group, so it has to opt in separately — miss that and the tab signs in
    // and is immediately logged out again on arrival.
    $cookie = sessionCookieFrom(
        $this->withoutVite()
            ->withUnencryptedCookie(TeamsEmbed::MARKER, '1')
            ->get('/admin/login'),
    );

    expect($cookie)->not->toBeNull()
        ->and($cookie->getSameSite())->toBe('none')
        ->and($cookie->isPartitioned())->toBeTrue();
});

it('allows only Teams to frame the app when embedded', function () {
    $policy = $this->withoutVite()->get('/teams')->headers->get('Content-Security-Policy');

    expect($policy)->toContain('frame-ancestors')
        ->and($policy)->toContain('https://teams.microsoft.com');
});

it('forbids framing the app from anywhere else', function () {
    $policy = $this->withoutVite()->get('/')->headers->get('Content-Security-Policy');

    expect($policy)->toBe("frame-ancestors 'self'")
        ->and($policy)->not->toContain('teams.microsoft.com');
});
