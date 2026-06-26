<?php

namespace App\Filament\Resources\Assets\Schemas;

use App\Enum\AssetCategory;
use App\Enum\AssetStatus;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AssetForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Item')
                    ->columns(2)
                    ->schema([
                        TextInput::make('asset_tag')
                            ->label('Asset tag')
                            ->placeholder('Auto-generated if left blank')
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),
                        Select::make('category')
                            ->options(AssetCategory::toArray())
                            ->required(),
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                        TextInput::make('brand')
                            ->maxLength(255),
                        TextInput::make('model')
                            ->maxLength(255),
                        TextInput::make('serial_number')
                            ->label('Serial number')
                            ->maxLength(255),
                        DatePicker::make('purchased_at')
                            ->label('Purchased on'),
                        Textarea::make('specifications')
                            ->placeholder('e.g. 8GB DDR4 3200MHz')
                            ->columnSpanFull(),
                    ]),

                Section::make('Status')
                    ->columns(2)
                    ->schema([
                        Select::make('status')
                            ->options(AssetStatus::toArray())
                            ->default(AssetStatus::AVAILABLE->value)
                            ->required(),
                        Textarea::make('notes')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
