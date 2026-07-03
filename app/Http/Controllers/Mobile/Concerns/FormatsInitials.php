<?php

namespace App\Http\Controllers\Mobile\Concerns;

use Illuminate\Support\Str;

trait FormatsInitials
{
    /**
     * Up to two uppercase initials from a name, for avatar fallbacks.
     */
    protected function initials(string $name): string
    {
        return Str::of($name)
            ->trim()
            ->explode(' ')
            ->filter()
            ->take(2)
            ->map(fn (string $part): string => Str::upper(Str::substr($part, 0, 1)))
            ->implode('');
    }
}
