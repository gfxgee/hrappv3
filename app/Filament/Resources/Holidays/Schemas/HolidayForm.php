<?php

namespace App\Filament\Resources\Holidays\Schemas;

use App\Enum\HolidayDuration;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class HolidayForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                DatePicker::make('date')
                    ->required()
                    ->unique(ignoreRecord: true),
                Select::make('duration')
                    ->options(HolidayDuration::options())
                    ->default(HolidayDuration::FULL_DAY->value)
                    ->required(),
                Toggle::make('is_active')
                    ->label('Active')
                    ->helperText('Inactive holidays are hidden and not observed.')
                    ->default(true),
            ]);
    }
}
