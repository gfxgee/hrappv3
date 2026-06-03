<?php

namespace App\Filament\Support;

use App\Support\TimeOptions;
use Filament\Forms\Components\Select;

class TimeSelect
{
    /**
     * A searchable quarter-hour time dropdown (00:00–24:00).
     *
     * `optionsLimit` is raised above the 97 generated options so the full
     * list is scrollable — Filament's default of 50 would cut it off at 12:15.
     */
    public static function make(string $name, ?string $default = null): Select
    {
        return Select::make($name)
            ->options(TimeOptions::quarterHours())
            ->searchable()
            ->native(false)
            ->optionsLimit(100)
            ->default($default);
    }
}
