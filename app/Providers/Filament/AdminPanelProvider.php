<?php

namespace App\Providers\Filament;

use App\Filament\Auth\EditProfile;
use App\Filament\Auth\Login;
use App\Filament\Pages\Dashboard;
use App\Filament\Pages\ManageGeneralSettings;
use App\Http\Middleware\RedirectMobileToApp;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationItem;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\View\View;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use ShuvroRoy\FilamentSpatieLaravelBackup\FilamentSpatieLaravelBackupPlugin;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->login(Login::class)
            ->passwordReset()
            ->sidebarCollapsibleOnDesktop()
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
            // Sidebar group order; ungrouped items (Dashboard, HR Overview) stay on top.
            ->navigationGroups([
                'My Workspace',
                'HR Management',
                'Recognition',
                'Access Control',
            ])
            // Settings lives in the user (avatar) menu, just under Profile.
            ->userMenuItems([
                'settings' => Action::make('settings')
                    ->label('Settings')
                    ->icon(Heroicon::OutlinedCog6Tooth)
                    ->url(fn (): string => ManageGeneralSettings::getUrl())
                    ->visible(fn (): bool => ManageGeneralSettings::canAccess())
                    ->sort(0),
            ])
            // A quick link to the employee's own payslip (a Google Sheet),
            // opened in a new tab. Only shows when they have one set. This is
            // the owner viewing their own link — HR/admins set it per employee.
            ->navigationItems([
                NavigationItem::make('Payslip')
                    ->group('My Workspace')
                    ->icon('heroicon-o-banknotes')
                    ->sort(15)
                    ->url(fn (): string => auth()->user()?->userData?->payslip_link ?? '#')
                    ->openUrlInNewTab()
                    ->visible(fn (): bool => filled(auth()->user()?->userData?->payslip_link)),
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->plugins([
                FilamentSpatieLaravelBackupPlugin::make()
                    ->authorize(fn (): bool => auth()->user() instanceof User && auth()->user()->isSuperAdmin())
                    ->navigationGroup('Access Control')
                    ->navigationLabel('Backups')
                    ->navigationIcon('heroicon-o-circle-stack')
                    ->navigationSort(99),
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
                RedirectMobileToApp::class,
            ])
            ->authMiddleware([
                Authenticate::class,
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
            // The Org Chart loads a JS charting library via @vite, which does
            // not re-execute on SPA (wire:navigate) transitions. Exclude it so
            // it always does a full page load and the chart renders reliably.
            ->spaUrlExceptions([
                '*/org-chart',
                '*/org-chart?*',
            ]);
    }
}
