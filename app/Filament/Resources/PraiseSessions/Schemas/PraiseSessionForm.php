<?php

namespace App\Filament\Resources\PraiseSessions\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class PraiseSessionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->placeholder('e.g. January 2026 Awards'),
                Toggle::make('is_active')
                    ->label('Active cycle')
                    ->helperText('Only one cycle is active at a time — activating this deactivates the others.'),
            ]);
    }
}
