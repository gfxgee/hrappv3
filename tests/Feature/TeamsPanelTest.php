<?php

use App\Filament\Pages\Dashboard;
use App\Filament\Pages\LeaveCalendar;
use App\Http\Middleware\TeamsEmbed;
use App\Models\Department;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Illuminate\Support\Collection;
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

/**
 * @return Collection<int, NavigationGroup>
 */
function labelledTeamsGroups(): Collection
{
    Filament::setCurrentPanel('teams-app');

    return collect(Filament::getNavigation())
        ->filter(fn (NavigationGroup $group): bool => filled($group->getLabel()))
        ->values();
}

it('keeps the Org Chart out of the bar, and puts the Leave Calendar behind the role', function () {
    // The Org Chart is something you go and look at rather than do, so it
    // stays out of the bar for everyone — still registered, still reachable
    // by URL. The Leave Calendar is gated to managers and team leaders, so
    // it belongs with the rest of the role-gated pages.
    $hr = User::factory()->create(['status' => 'active']);
    $hr->assignRole(Role::findOrCreate('hr'));

    $this->actingAs($hr->fresh());

    expect(teamsPanelNavigationLabels())
        ->not->toContain('Org Chart')
        ->and(LeaveCalendar::canAccess())->toBeTrue()
        ->and(teamsPanelNavigationLabels())->toContain('Leave Calendar');
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

it('gives an employee with no role a flat bar and no dropdown', function () {
    $this->actingAs(User::factory()->create(['status' => 'active']));
    Filament::setCurrentPanel('teams-app');

    expect(labelledTeamsGroups())->toBeEmpty();
});

it('gives someone with a role exactly one dropdown, holding what they can open', function () {
    // One extra item in a bar that already wraps to two rows — not twelve.
    $hr = User::factory()->create(['status' => 'active']);
    $hr->assignRole(Role::findOrCreate('hr'));

    $this->actingAs($hr->fresh());
    Filament::setCurrentPanel('teams-app');

    $groups = labelledTeamsGroups();

    expect($groups)->toHaveCount(1)
        ->and($groups->first()->getLabel())->toBe('Management');

    $inside = collect($groups->first()->getItems())
        ->map(fn ($item): string => (string) $item->getLabel());

    expect($inside)->toContain('Leave Requests')
        ->and($inside)->toContain('Overtime Requests')
        // Super-admin only, so an HR user must not see it.
        ->and($inside)->not->toContain('Roles');
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

it('leaves an /admin link alone inside the tab, because /admin is what the tab opens', function () {
    // KeepTeamsTabInItsPanel is unregistered while the tab lands on /admin:
    // rewriting these would bounce the tab into the panel it is staying out
    // of. Re-register it in the same move that points the tab back at
    // /teams-app, and this test flips back to asserting the redirect.
    $this->actingAs(User::factory()->create(['status' => 'active']));

    $this->withCookie(TeamsEmbed::MARKER, '1')
        ->withoutVite()
        ->get('/admin/daily-time-record')
        ->assertOk();
});

it('keeps the admin panel registered on /admin for the tab to land on', function () {
    // The Teams panel is still built and reachable at /teams-app; it is just
    // not where the tab goes. Both panels answering is what makes the switch
    // a one-line change rather than a redeploy of the design.
    expect(Route::has('filament.admin.pages.daily-time-record'))->toBeTrue()
        ->and(Route::has('filament.teams-app.pages.daily-time-record'))->toBeTrue();
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

it('shows each role only what that role can open', function () {
    // The per-item gate is each class's own canAccess(), so the dropdown
    // narrows and widens with the role rather than being a second list that
    // has to be kept in step by hand.
    //
    // Note what makes an approver: isTeamLeader() means *leads a department*,
    // not "holds the teamleader role". The role on its own opens nothing.
    $department = Department::factory()->create();

    $leader = User::factory()->create(['status' => 'active']);
    $leader->assignRole(Role::findOrCreate('teamleader'));
    $department->leaders()->syncWithoutDetaching([$leader->id]);

    $labels = function (): Collection {
        return labelledTeamsGroups()
            ->flatMap(fn (NavigationGroup $group): array => $group->getItems())
            ->map(fn ($item): string => (string) $item->getLabel())
            ->values();
    };

    $this->actingAs($leader->fresh());
    $leaderSees = $labels();

    $admin = User::factory()->create(['status' => 'active']);
    $admin->assignRole(Role::findOrCreate('super_admin'));

    $this->actingAs($admin->fresh());
    $adminSees = $labels();

    // Approvals reach a department leader; access control does not.
    expect($leaderSees)->toContain('Leave Requests')
        ->and($leaderSees)->not->toContain('Roles')
        ->and($adminSees)->toContain('Roles');
});

it('does not put two different "Suggestion Box" items in the same bar', function () {
    // The employee page is your own submission form; the resource is HR's
    // table of everyone's. Two sidebar groups apart in /admin, an inch apart
    // here, and identically named until now.
    $hr = User::factory()->create(['status' => 'active']);
    $hr->assignRole(Role::findOrCreate('hr'));

    $this->actingAs($hr->fresh());

    $inDropdown = labelledTeamsGroups()
        ->flatMap(fn (NavigationGroup $group): array => $group->getItems())
        ->map(fn ($item): string => (string) $item->getLabel());

    expect(teamsPanelNavigationLabels())->toContain('Suggestion Box')
        ->and($inDropdown)->toContain('All Suggestions')
        ->and($inDropdown)->not->toContain('Suggestion Box');
});
