<?php

use App\Filament\Pages\Dashboard;
use App\Filament\Pages\LeaveCalendar;
use App\Http\Middleware\TeamsEmbed;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Role;

/**
 * The Microsoft Teams tab has its own panel at /teams-app. It serves the same
 * pages as /admin, but with chrome that suits a tab inside Teams: top
 * navigation instead of a second sidebar, employee pages only, and no way to
 * sign out — the Microsoft popup is the only route back in, so a sign-out
 * button would simply strand whoever pressed it.
 */

/**
 * Every navigation label the Teams panel would render, groups flattened.
 *
 * @return list<string>
 */
function teamsPanelNavigationLabels(): array
{
    Filament::setCurrentPanel('teams-app');

    return collect(Filament::getNavigation())
        ->flatMap(fn (NavigationGroup $group): array => $group->getItems())
        ->map(fn (NavigationItem $item): string => $item->getLabel())
        ->values()
        ->all();
}

it('serves the dashboard to an active employee', function () {
    $this->actingAs(User::factory()->create(['status' => 'active']));

    $this->withoutVite()->get('/teams-app')->assertOk();
});

it('sends a guest back to the tab entry page rather than a login form', function () {
    // There is no login page inside the iframe to send anyone to; /teams
    // re-establishes the session from their Teams identity instead.
    $this->withoutVite()->get('/teams-app')->assertRedirect(route('teams.entry'));
});

it('offers no way to sign out of the Teams tab', function () {
    $this->actingAs(User::factory()->create(['status' => 'active']));
    Filament::setCurrentPanel('teams-app');

    $names = collect(Filament::getUserMenuItems())
        ->map(fn (Action $action): string => $action->getName())
        ->all();

    expect($names)->not->toContain('logout');
});

it('still offers sign out in the admin panel', function () {
    // Hiding it in the tab must not hide it for everybody.
    $this->actingAs(User::factory()->create(['status' => 'active']));
    Filament::setCurrentPanel('admin');

    $names = collect(Filament::getUserMenuItems())
        ->map(fn (Action $action): string => $action->getName())
        ->all();

    expect($names)->toContain('logout');
});

it('navigates to the employee self-service pages', function () {
    $this->actingAs(User::factory()->create(['status' => 'active']));

    expect(teamsPanelNavigationLabels())
        ->toContain('Dashboard')
        ->toContain('Daily Time Record')
        ->toContain('File Leave')
        ->toContain('File Overtime')
        ->toContain('File Attendance Correction')
        ->toContain('Praise Wall')
        ->toContain('Suggestion Box');
});

it('keeps the browsing pages out of the bar', function () {
    // The Org Chart and the Leave Calendar are things you go and look at, not
    // things you do in the half-minute someone spends in the tab. Both are
    // still registered and reachable by URL.
    $hr = User::factory()->create(['status' => 'active']);
    $hr->assignRole(Role::findOrCreate('hr'));

    $this->actingAs($hr->fresh());

    expect(teamsPanelNavigationLabels())
        ->not->toContain('Org Chart')
        // The HR role clears the Leave Calendar's own access check, so its
        // absence here is this panel's doing rather than a permission.
        ->and(LeaveCalendar::canAccess())->toBeTrue()
        ->and(teamsPanelNavigationLabels())->not->toContain('Leave Calendar');
});

it('keeps administration out of the tab navigation', function () {
    // Registered so dashboard widgets can still link to them, but never a
    // menu item: HR and admin work belongs in a browser, not a Teams tab.
    $this->actingAs(User::factory()->create(['status' => 'active']));

    expect(teamsPanelNavigationLabels())
        ->not->toContain('Users')
        ->not->toContain('Roles')
        ->not->toContain('Departments')
        ->not->toContain('Holidays')
        ->not->toContain('Backups')
        ->not->toContain('HR Overview')
        ->not->toContain('Import Attendance');
});

it('puts every item straight in the bar, with nothing behind a dropdown', function () {
    $this->actingAs(User::factory()->create(['status' => 'active']));
    Filament::setCurrentPanel('teams-app');

    $labelled = collect(Filament::getNavigation())
        ->filter(fn (NavigationGroup $group): bool => filled($group->getLabel()));

    expect($labelled)->toBeEmpty();
});

it('registers resources so dashboard links still resolve', function () {
    // The "on leave today" widget links to an employee record. Dropping the
    // resources from this panel would turn that into a 500.
    $this->actingAs(User::factory()->create(['status' => 'active']));
    Filament::setCurrentPanel('teams-app');

    expect(route('filament.teams-app.resources.users.view', ['record' => 1]))
        ->toContain('/teams-app/users/1');
});

it('treats the Teams panel as an embedded context', function () {
    config(['session.driver' => 'file']);

    $response = $this->actingAs(User::factory()->create(['status' => 'active']))
        ->withoutVite()
        ->get('/teams-app');

    $cookie = collect($response->headers->getCookies())
        ->first(fn ($cookie): bool => $cookie->getName() === (string) config('session.cookie'));

    expect($cookie)->not->toBeNull()
        ->and($cookie->getSameSite())->toBe('none')
        ->and($cookie->isPartitioned())->toBeTrue();
});

it('allows only Teams to frame the panel', function () {
    $response = $this->actingAs(User::factory()->create(['status' => 'active']))
        ->withoutVite()
        ->get('/teams-app');

    expect($response->headers->get('Content-Security-Policy'))
        ->toContain('frame-ancestors')
        ->toContain('https://teams.microsoft.com');
});

it('keeps links inside the tab rather than sending people to /admin', function () {
    // Filament generates URLs for the panel it is currently serving. If that
    // ever stopped holding, a quick action would bounce the employee out of
    // the tab and into the admin panel, logout button and all.
    $this->actingAs(User::factory()->create(['status' => 'active']));
    Filament::setCurrentPanel('teams-app');

    $urls = array_column((new Dashboard)->quickActions(), 'url');

    expect($urls)->not->toBeEmpty();

    foreach ($urls as $url) {
        expect($url)->toContain('/teams-app/')->not->toContain('/admin/');
    }
});

it('links the employee to their own payslip', function () {
    $user = User::factory()->create(['status' => 'active']);
    $user->userData()->create(['payslip_link' => 'https://docs.google.com/spreadsheets/d/abc']);

    $this->actingAs($user->fresh());

    expect(teamsPanelNavigationLabels())->toContain('Payslip');
});

it('hides the payslip link when the employee has none', function () {
    $this->actingAs(User::factory()->create(['status' => 'active']));

    expect(teamsPanelNavigationLabels())->not->toContain('Payslip');
});

it('renders a menu bar instead of a second sidebar', function () {
    // Teams already supplies an app rail down the left. A Filament sidebar
    // next to it would leave the actual page squeezed into what is left.
    $this->actingAs(User::factory()->create(['status' => 'active']));

    $this->withoutVite()->get('/teams-app')
        ->assertOk()
        ->assertSee('fi-body-has-top-navigation', escape: false);
});

it('pulls an /admin link followed inside the tab into the Teams panel', function () {
    // Stored notifications, pasted bookmarks and the dashboard route all
    // point at /admin. Following one must not load the full admin chrome,
    // logout button and all, inside the iframe.
    $this->actingAs(User::factory()->create(['status' => 'active']));

    $this->withCookie(TeamsEmbed::MARKER, '1')
        ->withoutVite()
        ->get('/admin/daily-time-record')
        ->assertRedirect(route('filament.teams-app.pages.daily-time-record'));
});

it('carries the record and query string across', function () {
    $this->actingAs(User::factory()->create(['status' => 'active']));

    $this->withCookie(TeamsEmbed::MARKER, '1')
        ->withoutVite()
        ->get('/admin/leave-requests/7/edit?activeTab=all')
        ->assertRedirect(route('filament.teams-app.resources.leave-requests.edit', [
            'record' => 7,
            'activeTab' => 'all',
        ]));
});

it('leaves an ordinary browser visit to /admin alone', function () {
    $this->actingAs(User::factory()->create(['status' => 'active']));

    $this->withoutVite()->get('/admin/daily-time-record')->assertOk();
});

it('leaves an admin-only page where it is when the tab has no counterpart', function () {
    // The backup manager is registered on /admin only. Redirecting it would
    // turn a working page into a 404.
    expect(Route::has('filament.teams-app.pages.backups'))->toBeFalse();
});
