<?php

use App\Http\Controllers\Auth\TeamsSsoController;
use App\Models\User;
use App\Services\TeamsSsoTokenVerifier;
use Illuminate\Support\Facades\Http;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

/**
 * Signing in from the Microsoft Teams tab. Teams supplies a token for whoever
 * is signed in to Teams; the app must prove that token is genuine before it
 * trusts the address inside it, and must still refuse anyone who is not an
 * active employee.
 */
function verifierReturns(?string $email): void
{
    test()->mock(
        TeamsSsoTokenVerifier::class,
        fn ($mock) => $mock->shouldReceive('emailFor')->andReturn($email),
    );
}

it('hands the tab a nonce to bind a popup sign-in to', function () {
    $response = $this->withoutVite()->get('/teams');

    $response->assertOk();
    expect(session(TeamsSsoController::NONCE_KEY))->toBeString()->not->toBeEmpty();
});

it('signs in an active employee from their Teams identity', function () {
    $user = User::factory()->create(['email' => 'employee@example.com', 'status' => 'active']);
    verifierReturns('employee@example.com');

    $this->postJson(route('teams.auth.token'), ['token' => 'a-teams-token'])
        ->assertOk()
        ->assertJson(['redirect' => url('/teams-app')]);

    $this->assertAuthenticatedAs($user);
});

it('sends an employee on a phone to the mobile app', function () {
    User::factory()->create(['email' => 'employee@example.com', 'status' => 'active']);
    verifierReturns('employee@example.com');

    $this->withHeader('User-Agent', iphoneUa())
        ->postJson(route('teams.auth.token'), ['token' => 'a-teams-token'])
        ->assertOk()
        ->assertJson(['redirect' => route('mobile.home')]);
});

it('refuses a Microsoft account with no employee record', function () {
    verifierReturns('stranger@example.com');

    $this->postJson(route('teams.auth.token'), ['token' => 'a-teams-token'])
        ->assertForbidden();

    $this->assertGuest();
});

it('refuses an employee who is no longer active', function () {
    User::factory()->create(['email' => 'former@example.com', 'status' => 'inactive']);
    verifierReturns('former@example.com');

    $this->postJson(route('teams.auth.token'), ['token' => 'a-teams-token'])
        ->assertForbidden();

    $this->assertGuest();
});

it('asks the tab to try the popup when the token cannot be verified', function () {
    verifierReturns(null);

    $this->postJson(route('teams.auth.token'), ['token' => 'a-teams-token'])
        ->assertUnauthorized()
        ->assertJson(['retry' => 'popup']);

    $this->assertGuest();
});

it('never signs anyone in on an unverified token', function () {
    User::factory()->create(['email' => 'employee@example.com', 'status' => 'active']);
    verifierReturns(null);

    $this->postJson(route('teams.auth.token'), ['token' => 'forged'])->assertUnauthorized();

    $this->assertGuest();
});

/*
| The popup fallback. The popup is a top-level window, so it cannot create the
| tab's session itself - it returns a single-use code that the tab redeems.
*/

it('redeems a popup code for a session', function () {
    $user = User::factory()->create(['email' => 'employee@example.com', 'status' => 'active']);
    $code = TeamsSsoController::issueClaim($user, 'the-nonce');

    $this->withSession([TeamsSsoController::NONCE_KEY => 'the-nonce'])
        ->postJson(route('teams.auth.claim'), ['code' => $code])
        ->assertOk()
        ->assertJson(['redirect' => url('/teams-app')]);

    $this->assertAuthenticatedAs($user);
});

it('refuses a code redeemed from a different tab', function () {
    $user = User::factory()->create(['status' => 'active']);
    $code = TeamsSsoController::issueClaim($user, 'the-nonce');

    $this->withSession([TeamsSsoController::NONCE_KEY => 'a-different-tab'])
        ->postJson(route('teams.auth.claim'), ['code' => $code])
        ->assertUnauthorized();

    $this->assertGuest();
});

it('only lets a popup code be used once', function () {
    $user = User::factory()->create(['status' => 'active']);
    $code = TeamsSsoController::issueClaim($user, 'the-nonce');

    $this->withSession([TeamsSsoController::NONCE_KEY => 'the-nonce'])
        ->postJson(route('teams.auth.claim'), ['code' => $code])
        ->assertOk();

    auth()->logout();

    $this->withSession([TeamsSsoController::NONCE_KEY => 'the-nonce'])
        ->postJson(route('teams.auth.claim'), ['code' => $code])
        ->assertUnauthorized();
});

it('refuses a made-up code', function () {
    $this->withSession([TeamsSsoController::NONCE_KEY => 'the-nonce'])
        ->postJson(route('teams.auth.claim'), ['code' => 'not-a-real-code'])
        ->assertUnauthorized();

    $this->assertGuest();
});

it('hands the popup a code instead of signing in there', function () {
    User::factory()->create(['email' => 'employee@example.com', 'status' => 'active']);

    Socialite::fake('microsoft', (new SocialiteUser)->map([
        'id' => 'ms-1',
        'name' => 'SSO User',
        'email' => 'employee@example.com',
    ]));

    $this->withoutVite()
        ->withSession(['sso_teams_nonce' => 'the-nonce'])
        ->get(route('sso.microsoft.callback'))
        ->assertOk()
        ->assertViewIs('teams.popup')
        ->assertViewHas('code');

    // The session must be created in the tab, not in the popup window.
    $this->assertGuest();
});

it('tells the popup when the account is not an employee', function () {
    Socialite::fake('microsoft', (new SocialiteUser)->map([
        'id' => 'ms-2',
        'name' => 'Stranger',
        'email' => 'stranger@example.com',
    ]));

    $this->withoutVite()
        ->withSession(['sso_teams_nonce' => 'the-nonce'])
        ->get(route('sso.microsoft.callback'))
        ->assertOk()
        ->assertViewIs('teams.popup')
        ->assertViewHas('error');

    $this->assertGuest();
});

it('remembers the tab nonce across the Microsoft round trip', function () {
    Socialite::fake('microsoft');

    $this->get(route('sso.microsoft.redirect', ['teams_nonce' => 'the-nonce']))
        ->assertRedirect();

    expect(session('sso_teams_nonce'))->toBe('the-nonce');
});

/*
| Token verification itself. A token is only trusted because Entra accepted it
| in an on-behalf-of exchange, which it does only for tokens issued to this app.
*/

it('reads the verified address from Microsoft Graph', function () {
    config(['services.microsoft.client_id' => 'client', 'services.microsoft.client_secret' => 'secret']);

    Http::fake([
        'login.microsoftonline.com/*' => Http::response(['access_token' => 'graph-token']),
        'graph.microsoft.com/*' => Http::response(['mail' => 'employee@example.com']),
    ]);

    expect(app(TeamsSsoTokenVerifier::class)->emailFor('a-teams-token'))
        ->toBe('employee@example.com');
});

it('falls back to the user principal name when there is no mailbox', function () {
    config(['services.microsoft.client_id' => 'client', 'services.microsoft.client_secret' => 'secret']);

    Http::fake([
        'login.microsoftonline.com/*' => Http::response(['access_token' => 'graph-token']),
        'graph.microsoft.com/*' => Http::response(['mail' => null, 'userPrincipalName' => 'upn@example.com']),
    ]);

    expect(app(TeamsSsoTokenVerifier::class)->emailFor('a-teams-token'))->toBe('upn@example.com');
});

it('rejects a token Entra will not exchange', function () {
    config(['services.microsoft.client_id' => 'client', 'services.microsoft.client_secret' => 'secret']);

    Http::fake([
        'login.microsoftonline.com/*' => Http::response(['error' => 'invalid_grant'], 400),
    ]);

    expect(app(TeamsSsoTokenVerifier::class)->emailFor('forged'))->toBeNull();
});

it('does not call Microsoft at all when no credentials are configured', function () {
    config(['services.microsoft.client_id' => '', 'services.microsoft.client_secret' => '']);

    Http::fake();

    expect(app(TeamsSsoTokenVerifier::class)->emailFor('a-teams-token'))->toBeNull();

    Http::assertNothingSent();
});
