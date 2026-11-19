<?php

namespace App\Providers\Filament;

use App\Filament\Auth\EditProfile;
use App\Filament\Pages\DailyTimeRecord;
use App\Filament\Pages\Dashboard;
use App\Filament\Pages\FileAttendanceCorrection;
use App\Filament\Pages\FileLeaveRequest;
use App\Filament\Pages\FileOverTimeRequest;
use App\Filament\Pages\LeaveCalendar;
use App\Filament\Pages\OrgChart;
use App\Filament\Pages\PraiseWall;
use App\Filament\Pages\SuggestionBox;
use App\Http\Middleware\AuthenticateTeamsTab;
use App\Http\Middleware\RedirectMobileToApp;
use App\Http\Middleware\TeamsEmbed;
use Filament\Actions\Action;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationBuilder;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\View\View;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * The Microsoft Teams tab surface.
 *
 * Same application, different chrome. Teams already supplies an app rail, a
 * header and a way back out, so this panel drops the parts of /admin that
 * duplicate or fight with it:
 *
 * - Top navigation, not a sidebar, so the tab is not two rails wide.
 * - Only the pages an employee uses themselves. HR and administration stay
 *   at /admin in a real browser, where their tables have room to breathe.
 * - No sign-out. There is no way back in from inside the tab: the Microsoft
 *   popup is the only route, so signing out would simply strand them.
 *
 * Resources are still registered even though none of them appear in the
 * navigation, because dashboard widgets link to them (an employee record from
 * "on leave today", for instance) and those links have to resolve.
 */
class TeamsPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('teams-app')
            ->path('teams-app')
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->topNavigation()
            ->profile(EditProfile::class, isSimple: false)
            ->databaseNotifications()
            ->databaseNotificationsPolling('30s')
            ->maxContentWidth(Width::Full)
            ->font('Inter')
            ->colors([
                'primary' => Color::Amber,
                'verified' => Color::hex('#1e1242'),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            // Replaces Filament's generated navigation outright, so registering
            // a resource above does not put it in the tab's menu bar.
            ->navigation(fn (NavigationBuilder $builder): NavigationBuilder => $builder
                ->items([
                    ...Dashboard::getNavigationItems(),
                    ...DailyTimeRecord::getNavigationItems(),
                    ...LeaveCalendar::getNavigationItems(),
                    ...PraiseWall::getNavigationItems(),
                    ...SuggestionBox::getNavigationItems(),
                    ...OrgChart::getNavigationItems(),
                    // The employee's own payslip (a Google Sheet). Teams opens
                    // an external link in the default browser, which is where
                    // a spreadsheet belongs anyway.
                    NavigationItem::make('Payslip')
                        ->icon('heroicon-o-banknotes')
                        ->url(fn (): string => auth()->user()?->userData?->payslip_link ?? '#')
                        ->openUrlInNewTab()
                        ->visible(fn (): bool => filled(auth()->user()?->userData?->payslip_link)),
                ])
                // Collapsed into one dropdown: three form pages side by side
                // would crowd a menu bar that has to fit a Teams tab.
                ->group(NavigationGroup::make('Requests')->items([
                    ...FileLeaveRequest::getNavigationItems(),
                    ...FileOverTimeRequest::getNavigationItems(),
                    ...FileAttendanceCorrection::getNavigationItems(),
                ]))
            )
            // Hidden rather than removed: Filament adds the logout item itself,
            // so the key has to be claimed to suppress it.
            ->userMenuItems([
                'logout' => fn (Action $action): Action => $action->hidden(),
            ])
            ->middleware([
                // Ahead of StartSession, and of EncryptCookies so its marker
                // is read raw — see AdminPanelProvider for why.
                TeamsEmbed::class,
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
                // Teams on a phone gets the mobile app, the same as everywhere
                // else — it is a better fit than a menu bar on a small screen.
                RedirectMobileToApp::class,
            ])
            ->authMiddleware([
                AuthenticateTeamsTab::class,
            ])
            ->unsavedChangesAlerts()
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): View => view('components.google-analytics'),
            )
            ->renderHook(
                PanelsRenderHook::PAGE_START,
                fn (): View => view('filament.announcements.banners'),
            )
            ->spa(hasPrefetching: true)
            // See AdminPanelProvider: the Org Chart's charting library does not
            // re-execute on wire:navigate transitions, and the mobile app is a
            // separate Inertia app that SPA navigation cannot boot.
            ->spaUrlExceptions([
                '*/org-chart',
                '*/org-chart?*',
                '*/m',
                '*/m/*',
            ]);
    }
}
