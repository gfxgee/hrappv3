<?php

namespace App\Providers\Filament;

use App\Filament\Auth\EditProfile;
use App\Filament\Pages\DailyTimeRecord;
use App\Filament\Pages\Dashboard;
use App\Filament\Pages\FileAttendanceCorrection;
use App\Filament\Pages\FileLeaveRequest;
use App\Filament\Pages\FileOverTimeRequest;
use App\Filament\Pages\HrOverview;
use App\Filament\Pages\ImportAttendance;
use App\Filament\Pages\LeaveCalendar;
use App\Filament\Pages\ManageGeneralSettings;
use App\Filament\Pages\PraiseWall;
use App\Filament\Pages\SuggestionBox;
use App\Filament\Resources\Activities\ActivityResource;
use App\Filament\Resources\Announcements\AnnouncementResource;
use App\Filament\Resources\Assets\AssetResource;
use App\Filament\Resources\AttendanceCorrectionRequests\AttendanceCorrectionRequestResource;
use App\Filament\Resources\Badges\BadgeResource;
use App\Filament\Resources\Departments\DepartmentResource;
use App\Filament\Resources\Holidays\HolidayResource;
use App\Filament\Resources\LeaveRequests\LeaveRequestResource;
use App\Filament\Resources\MoodCheckIns\MoodCheckInResource;
use App\Filament\Resources\OnCallAssignments\OnCallAssignmentResource;
use App\Filament\Resources\OnCallMembers\OnCallMemberResource;
use App\Filament\Resources\OverTimeRequests\OverTimeRequestResource;
use App\Filament\Resources\PraiseSessions\PraiseSessionResource;
use App\Filament\Resources\Roles\RoleResource;
use App\Filament\Resources\Suggestions\SuggestionResource;
use App\Filament\Resources\Users\UserResource;
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
    /**
     * Labels that collide with an employee page once both sit in the same
     * menu bar. In /admin they are two sidebar groups apart; here they would
     * be an inch apart and point at different things — the employee's own
     * submission form, and HR's table of everyone's.
     *
     * @var array<class-string, string>
     */
    private const RELABELLED = [
        SuggestionResource::class => 'All Suggestions',
    ];

    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('teams-app')
            ->path('teams-app')
            // The tab's own theme, not the admin one. Keeps the two surfaces
            // free to diverge, and makes bringing this look to /admin a
            // one-line change there rather than an unpicking job here.
            ->viteTheme('resources/css/filament/teams/theme.css')
            ->topNavigation()
            ->profile(EditProfile::class, isSimple: false)
            ->databaseNotifications()
            ->databaseNotificationsPolling('30s')
            ->maxContentWidth(Width::Full)
            ->font('Instrument Sans')
            ->colors([
                'primary' => Color::hex('#592bd7'),
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
                    // Filing leave and overtime is what people open the tab
                    // for, so both sit in the bar rather than behind a menu.
                    ...FileLeaveRequest::getNavigationItems(),
                    ...FileOverTimeRequest::getNavigationItems(),
                    ...FileAttendanceCorrection::getNavigationItems(),
                    ...PraiseWall::getNavigationItems(),
                    ...SuggestionBox::getNavigationItems(),
                    // The employee's own payslip (a Google Sheet). Teams opens
                    // an external link in the default browser, which is where
                    // a spreadsheet belongs anyway.
                    NavigationItem::make('Payslip')
                        ->icon('heroicon-o-banknotes')
                        ->url(fn (): string => auth()->user()?->userData?->payslip_link ?? '#')
                        ->openUrlInNewTab()
                        ->visible(fn (): bool => filled(auth()->user()?->userData?->payslip_link)),
                ])
                ->groups($this->managementGroup())
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
            // Counts the dashboard figures up on arrival. Loaded for this
            // panel only, so /admin keeps its current, motionless chrome.
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): View => view('filament.teams.motion'),
            )
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): View => view('filament.shared.select-scroll'),
            )
            // The tab's own drawing of the same announcements: a one-line
            // notice that opens on a click, so a long one does not push the
            // dashboard below the fold. /admin keeps the full banner.
            ->renderHook(
                PanelsRenderHook::PAGE_START,
                fn (): View => view('filament.announcements.teams-banners'),
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

    /**
     * The role-gated pages, as a single dropdown in the menu bar.
     *
     * Access was never the problem: this panel discovers the same resources
     * and pages as /admin, and each one gates itself — isManager(),
     * isTeamLeader(), isSuperAdmin(), or a policy. An HR employee could
     * already open /teams-app/leave-requests directly. There was simply
     * nothing linking to it.
     *
     * Flat items were not an option: the bar already wraps to two rows with
     * the seven employee pages, and HR Management alone has twelve. One
     * labelled group renders as a dropdown in top navigation, so people with
     * a role gain a single item and everyone else gains nothing.
     *
     * Each item reuses the class's own getNavigationItems() — label, icon and
     * badge included — and is dropped when canAccess() says no. A custom
     * NavigationBuilder bypasses Filament's own filtering, so the gate has to
     * be applied here; asking the class rather than re-deriving the roles is
     * what keeps the menu from drifting away from what it opens.
     *
     * @return array<NavigationGroup>
     */
    protected function managementGroup(): array
    {
        /** @var list<class-string> $managed */
        $managed = [
            // What approvers open most days.
            LeaveRequestResource::class,
            OverTimeRequestResource::class,
            AttendanceCorrectionRequestResource::class,
            LeaveCalendar::class,
            HrOverview::class,
            // People and the shape of the team.
            UserResource::class,
            DepartmentResource::class,
            OnCallAssignmentResource::class,
            OnCallMemberResource::class,
            HolidayResource::class,
            // What HR publishes and reads back.
            AnnouncementResource::class,
            SuggestionResource::class,
            MoodCheckInResource::class,
            PraiseSessionResource::class,
            BadgeResource::class,
            // Administration.
            AssetResource::class,
            ImportAttendance::class,
            RoleResource::class,
            ActivityResource::class,
            ManageGeneralSettings::class,
        ];

        // The dropdown is what a role buys you, so someone with no role at all
        // never sees it. Without this the employee directory would put it in
        // everyone's bar on its own: UserPolicy::viewAny() is deliberately
        // open — the employee list is a directory — and only writing to it
        // needs a role. Same notion of "has a role" as UserPolicy::canManage().
        if (auth()->user()?->roles()->exists() !== true) {
            return [];
        }

        $items = [];

        foreach ($managed as $sort => $class) {
            if (! $class::canAccess()) {
                continue;
            }

            foreach ($class::getNavigationItems() as $item) {
                // Drop the class's own group — that is /admin's sidebar
                // grouping — and keep the order listed above.
                $item = $item->group(null)->sort($sort);

                if (array_key_exists($class, self::RELABELLED)) {
                    $item = $item->label(self::RELABELLED[$class]);
                }

                $items[] = $item;
            }
        }

        // No dropdown at all for an employee with no role.
        return $items === [] ? [] : [NavigationGroup::make('Management')->items($items)];
    }
}
