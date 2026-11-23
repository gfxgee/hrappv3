<?php

use App\Enum\AttendanceStatus;
use App\Filament\Pages\Dashboard;
use App\Filament\Widgets\Teams\LeaveBalanceStatsWidget;
use App\Models\OverTimeRequest;
use App\Models\User;
use Filament\Facades\Filament;

/**
 * The Teams tab's dashboard is the same page object as /admin's, skinned for
 * the tab: its own view, its own stat cards, and one on-call surface instead
 * of two. These tests pin the parts that would otherwise drift apart silently
 * — a view that stops being panel-aware looks fine until someone opens Teams.
 */
function teamsEmployee(array $leave = []): User
{
    $user = User::factory()->create(['status' => 'active']);

    $user->userData()->create([
        'time_in' => '09:00',
        'time_out' => '18:00',
        ...$leave,
    ]);

    return $user->fresh();
}

it('renders the Teams view in the Teams panel and the admin view in /admin', function () {
    $page = new Dashboard;

    Filament::setCurrentPanel('teams-app');
    expect($page->getView())->toBe('filament.pages.teams.dashboard');

    Filament::setCurrentPanel('admin');
    expect($page->getView())->toBe('filament.pages.dashboard');
});

it('states on-call once, not as a banner as well as a card', function () {
    // The admin dashboard says it twice, which collides for exactly the
    // person it addresses. The Teams card carries both jobs instead.
    $view = file_get_contents(resource_path('views/filament/pages/teams/dashboard.blade.php'));

    expect($view)
        ->not->toContain('myOnCallNotice')
        ->and($view)->toContain('Widgets\Teams\OnCallWidget');
});

it('keeps every widget the admin dashboard shows', function () {
    $admin = file_get_contents(resource_path('views/filament/pages/dashboard.blade.php'));
    $teams = file_get_contents(resource_path('views/filament/pages/teams/dashboard.blade.php'));

    preg_match_all('/@livewire\(\\\\App\\\\Filament\\\\Widgets\\\\([A-Za-z\\\\]+)::class\)/', $admin, $matches);

    // The Teams panel has its own drawing of two of them; the rest must all
    // still be here. Dropping a widget to tidy a layout is how people lose
    // the thing they opened the tab for.
    $swapped = ['Employee\EmployeeStatsWidget', 'Employee\OnCallWidget'];

    foreach (array_diff($matches[1], $swapped) as $widget) {
        expect($teams)->toContain($widget);
    }

    expect($teams)
        ->toContain('Teams\LeaveBalanceStatsWidget')
        ->and($teams)->toContain('Teams\OnCallWidget');
});

it('shows the stat cards in the Teams panel only', function () {
    $this->actingAs(teamsEmployee());

    Filament::setCurrentPanel('teams-app');
    expect(LeaveBalanceStatsWidget::canView())->toBeTrue();

    Filament::setCurrentPanel('admin');
    expect(LeaveBalanceStatsWidget::canView())->toBeFalse();
});

it('fills each bar with the proportion its note states', function () {
    $user = teamsEmployee(['vacation_leave' => 15, 'sick_leave' => 7]);
    $this->actingAs($user);
    Filament::setCurrentPanel('teams-app');

    $stats = collect((new LeaveBalanceStatsWidget)->stats())->keyBy('label');

    expect($stats['Leave left']['value'])->toBe('15')
        ->and($stats['Leave left']['unit'])->toBe('of 15 days')
        ->and($stats['Leave left']['fill'])->toBe(100.0)
        ->and($stats['Sick days']['fill'])->toBe(100.0);
});

it('splits the overtime bar between approved hours and hours still with an approver', function () {
    $user = teamsEmployee();
    $this->actingAs($user);
    Filament::setCurrentPanel('teams-app');

    OverTimeRequest::factory()->create([
        'user_id' => $user->id,
        'request_date' => now()->startOfMonth()->addDay(),
        'hours' => 6,
        'status' => AttendanceStatus::APPROVED->value,
    ]);

    OverTimeRequest::factory()->create([
        'user_id' => $user->id,
        'request_date' => now()->startOfMonth()->addDays(2),
        'hours' => 2,
        'status' => AttendanceStatus::FOR_APPROVAL->value,
    ]);

    $overtime = collect((new LeaveBalanceStatsWidget)->stats())
        ->firstWhere('label', 'Overtime this month');

    expect($overtime['value'])->toBe('6')
        ->and($overtime['note'])->toBe('2 more awaiting approval')
        ->and($overtime['fill'])->toBe(75.0)
        ->and($overtime['fill2'])->toBe(25.0);
});

it('draws no overtime bar when nothing has been filed', function () {
    $this->actingAs(teamsEmployee());
    Filament::setCurrentPanel('teams-app');

    $overtime = collect((new LeaveBalanceStatsWidget)->stats())
        ->firstWhere('label', 'Overtime this month');

    expect($overtime['fill'])->toBe(0.0)
        ->and($overtime['accent2'])->toBeNull()
        ->and($overtime['note'])->toBe('Nothing awaiting approval');
});

it('carries the finished figures in the markup so a tab with no motion still reads true', function () {
    // teams-motion.ts only ever replays a value that is already correct.
    $view = file_get_contents(resource_path('views/filament/widgets/teams/leave-balance-stats-widget.blade.php'));

    expect($view)
        ->toContain('data-reveal')
        ->and($view)->toContain('data-count-to')
        ->and($view)->toContain('data-grow-to')
        ->and($view)->toContain(">{{ \$stat['value'] }}<");
});

it('loads the motion script and the Teams theme for the Teams panel only', function () {
    $teams = file_get_contents(app_path('Providers/Filament/TeamsPanelProvider.php'));
    $admin = file_get_contents(app_path('Providers/Filament/AdminPanelProvider.php'));

    expect($teams)
        ->toContain("viteTheme('resources/css/filament/teams/theme.css')")
        ->and($teams)->toContain('filament.teams.motion')
        ->and($admin)->not->toContain('filament.teams.motion')
        ->and($admin)->not->toContain('filament/teams/theme.css');
});

it('drops the "Dashboard" title in the Teams tab so the greeting band is the header', function () {
    $page = new Dashboard;

    Filament::setCurrentPanel('teams-app');
    expect($page->getHeading())->toBe('');

    Filament::setCurrentPanel('admin');
    expect($page->getHeading())->toBe('Dashboard');
});

it('skins the tab in dark as well as light', function () {
    // Teams carries its own theme and plenty of people run it dark. Without a
    // dark palette the tab falls through to stock Filament chrome, which is
    // the one state where none of the design is visible.
    $theme = file_get_contents(resource_path('css/filament/teams/theme.css'));

    expect($theme)
        ->toContain('.dark {')
        ->and($theme)->toMatch('/\.dark \{[^}]*--hr-ground:/s')
        ->and($theme)->toMatch('/\.dark \{[^}]*--hr-card:/s');
});

it('paints the topbar on .fi-topbar itself, which is the nav element', function () {
    // `.fi-topbar nav` matched nothing: Filament renders the topbar AS the
    // <nav>, so the descendant selector silently painted no band at all.
    $theme = file_get_contents(resource_path('css/filament/teams/theme.css'));

    expect($theme)
        ->toContain('.fi-topbar {')
        ->and($theme)->not->toContain('.fi-topbar nav');
});

it('runs the greeting band edge to edge', function () {
    $view = file_get_contents(resource_path('views/filament/pages/teams/dashboard.blade.php'));

    // Guessed negative margins left a strip of ground showing down each side.
    expect($view)
        ->toContain('hr-bleed')
        ->and($view)->not->toContain('-mx-4');
});

it('cancels exactly the padding Filament applies, at every breakpoint', function () {
    // .hr-bleed mirrors these vendor values. If Filament changes them the
    // band grows a gap again, silently — so fail here instead, and say so.
    $layout = file_get_contents(base_path('vendor/filament/filament/resources/css/components/layout.css'));
    $page = file_get_contents(base_path('vendor/filament/filament/resources/css/components/page.css'));
    $theme = file_get_contents(resource_path('css/filament/teams/theme.css'));

    expect($layout)->toContain('px-4 md:px-6 lg:px-8')
        ->and($page)->toContain('gap-y-8 py-8');

    // 1rem / 1.5rem / 2rem inline, 2rem top — the same values, negated.
    expect($theme)
        ->toContain('margin-inline: -1rem')
        ->and($theme)->toContain('margin-top: -2rem')
        ->and($theme)->toContain('margin-inline: -1.5rem')
        ->and($theme)->toContain('margin-inline: -2rem')
        ->and($theme)->toContain('@media (min-width: 48rem)')
        ->and($theme)->toContain('@media (min-width: 64rem)');
});

it('reads the current nav item against the band, not against a white topbar', function () {
    // Filament's active item is `bg-gray-50` with `text-primary-600`, built
    // for a white topbar. Left alone on the purple band that is white text
    // on a white pill, and the icons and brand mark stay near-black.
    $vendor = file_get_contents(base_path('vendor/filament/filament/resources/css/components/topbar.css'));
    $logo = file_get_contents(base_path('vendor/filament/filament/resources/css/components/logo.css'));
    $theme = file_get_contents(resource_path('css/filament/teams/theme.css'));

    expect($vendor)->toContain('bg-gray-50 dark:bg-white/5')
        ->and($vendor)->toContain('text-gray-400 dark:text-gray-500')
        ->and($logo)->toContain('text-gray-950');

    expect($theme)
        ->toContain('.fi-topbar .fi-topbar-item.fi-active .fi-topbar-item-btn {')
        ->and($theme)->toContain('.fi-topbar .fi-topbar-item.fi-active .fi-topbar-item-btn > .fi-icon')
        ->and($theme)->toContain('.fi-topbar .fi-topbar-item-btn > .fi-icon')
        ->and($theme)->toContain('.fi-topbar .fi-logo');
});
