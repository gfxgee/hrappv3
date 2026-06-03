<?php

namespace App\Filament\Resources\Badges\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class BadgeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('label')
                    ->required()
                    ->maxLength(255),
                TextInput::make('icon')
                    ->helperText('An emoji (🏆) or a Heroicon name.')
                    ->maxLength(255),
                Select::make('color')
                    ->options([
                        'primary' => 'Primary',
                        'success' => 'Success',
                        'warning' => 'Warning',
                        'danger' => 'Danger',
                        'info' => 'Info',
                        'gray' => 'Gray',
                    ])
                    ->default('primary')
                    ->required(),
                TextInput::make('points')
                    ->numeric()
                    ->minValue(0)
                    ->default(0)
                    ->helperText('Used for recognition leaderboards.'),
                Toggle::make('is_active')
                    ->label('Active')
                    ->default(true)
                    ->helperText('Inactive badges cannot be chosen on new praises.'),
            ]);
    }
}
