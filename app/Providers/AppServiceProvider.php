<?php

namespace App\Providers;

use App\Http\Responses\FilamentLoginResponse;
use Carbon\CarbonImmutable;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Lab404\Impersonate\Events\LeaveImpersonation;
use Lab404\Impersonate\Events\TakeImpersonation;
use SocialiteProviders\Manager\SocialiteWasCalled;
use SocialiteProviders\Microsoft\Provider as MicrosoftProvider;
use Spatie\Browsershot\Browsershot;
use Spatie\LaravelPdf\Facades\Pdf;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Route phones and regular employees to the mobile app after login,
        // instead of Filament's default admin-panel redirect.
        $this->app->bind(
            LoginResponse::class,
            FilamentLoginResponse::class,
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureSocialite();
        $this->configurePdf();
        $this->configureActivityLog();
        $this->configureImpersonation();
    }

    /**
     * Keep impersonation working alongside Filament's AuthenticateSession
     * middleware, which logs a user out when the session's stored password
     * hash no longer matches the authenticated user. When impersonation starts
     * or ends the authenticated user changes, so realign the stored hash to the
     * now-current user. Also record start/stop in the activity log.
     */
    protected function configureImpersonation(): void
    {
        $passwordHashKey = 'password_hash_'.config('auth.defaults.guard');

        Event::listen(function (TakeImpersonation $event) use ($passwordHashKey): void {
            session()->put($passwordHashKey, $event->impersonated->getAuthPassword());

            activity('auth')
                ->causedBy($event->impersonator)
                ->performedOn($event->impersonated)
                ->event('impersonate')
                ->log('Started impersonating');
        });

        Event::listen(function (LeaveImpersonation $event) use ($passwordHashKey): void {
            session()->put($passwordHashKey, $event->impersonator->getAuthPassword());

            activity('auth')
                ->causedBy($event->impersonator)
                ->performedOn($event->impersonated)
                ->event('leave_impersonation')
                ->log('Stopped impersonating');
        });
    }

    /**
     * Record authentication activity (sign in, sign out, failed attempts) to
     * the activity log under the "auth" log name.
     */
    protected function configureActivityLog(): void
    {
        Event::listen(function (Login $event): void {
            activity('auth')->causedBy($event->user)->event('login')->log('Signed in');
        });

        Event::listen(function (Logout $event): void {
            if ($event->user !== null) {
                activity('auth')->causedBy($event->user)->event('logout')->log('Signed out');
            }
        });

        Event::listen(function (Failed $event): void {
            activity('auth')
                ->withProperties(['email' => $event->credentials['email'] ?? null])
                ->event('failed_login')
                ->log('Failed sign-in');
        });
    }

    /**
     * Point Browsershot (PDF rendering) at the configured Chrome binary so it
     * doesn't rely on auto-detection, which fails on some setups (Windows/Herd).
     * Applied to every Pdf::view() via the shared default builder.
     */
    protected function configurePdf(): void
    {
        $chromePath = config('services.chrome.path');
        $nodeBinary = config('services.chrome.node_binary');
        $npmBinary = config('services.chrome.npm_binary');

        if (blank($chromePath) && blank($nodeBinary) && blank($npmBinary)) {
            return;
        }

        Pdf::default()->withBrowsershot(function (Browsershot $browsershot) use ($chromePath, $nodeBinary, $npmBinary): void {
            if (filled($chromePath)) {
                $browsershot->setChromePath($chromePath);
            }

            if (filled($nodeBinary)) {
                $browsershot->setNodeBinary($nodeBinary);
            }

            if (filled($npmBinary)) {
                $browsershot->setNpmBinary($npmBinary);
            }
        });
    }

    /**
     * Register community Socialite providers (Microsoft / Azure AD).
     */
    protected function configureSocialite(): void
    {
        Event::listen(function (SocialiteWasCalled $event): void {
            $event->extendSocialite('microsoft', MicrosoftProvider::class);
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
