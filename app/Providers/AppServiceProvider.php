<?php

namespace App\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
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
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureSocialite();
        $this->configurePdf();
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
