<?php

namespace App\Filament\Auth;

use Filament\Auth\Pages\Login as BaseLogin;

/**
 * Custom split-screen login: the form lives on the left, a brand image
 * carousel on the right. All authentication behaviour (validation, rate
 * limiting, two-factor, passkeys, redirects) is inherited from Filament's
 * base Login page — only the presentation changes.
 */
class Login extends BaseLogin
{
    protected string $view = 'filament.auth.login';

    /**
     * Render directly inside Filament's base layout (full-screen, no centered
     * card) so the view owns the whole viewport for the split layout.
     */
    protected static string $layout = 'filament-panels::components.layout.base';

    /**
     * Brand name shown on the left panel.
     */
    public function brandName(): string
    {
        return config('app.name') === 'Laravel' ? 'Digitalfeet.HR' : (string) config('app.name');
    }

    /**
     * Image URLs for the right-side carousel. Drop images into
     * public/images/login/ and they appear automatically; when the folder is
     * empty, the view falls back to a branded gradient panel.
     *
     * @return list<string>
     */
    public function carouselImages(): array
    {
        $directory = public_path('images/login');

        if (! is_dir($directory)) {
            return [];
        }

        return collect(scandir($directory) ?: [])
            ->reject(fn (string $file): bool => in_array($file, ['.', '..'], true))
            ->filter(fn (string $file): bool => in_array(
                strtolower(pathinfo($file, PATHINFO_EXTENSION)),
                ['jpg', 'jpeg', 'png', 'webp', 'gif'],
                true,
            ))
            ->map(fn (string $file): string => asset('images/login/'.$file))
            ->values()
            ->all();
    }
}
