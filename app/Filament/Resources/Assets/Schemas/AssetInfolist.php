<?php

namespace App\Filament\Resources\Assets\Schemas;

use App\Enum\AssetCategory;
use App\Enum\AssetStatus;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AssetInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Item')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('asset_tag')
                            ->label('Asset tag'),
                        TextEntry::make('category')
                            ->badge()
                            ->formatStateUsing(fn (AssetCategory $state): string => $state->label()),
                        TextEntry::make('name')
                            ->columnSpanFull(),
                        TextEntry::make('brand')
                            ->placeholder('—'),
                        TextEntry::make('model')
                            ->placeholder('—'),
                        TextEntry::make('serial_number')
                            ->label('Serial number')
                            ->placeholder('—'),
                        TextEntry::make('purchased_at')
                            ->label('Purchased on')
                            ->date()
                            ->placeholder('—'),
                        TextEntry::make('specifications')
                            ->placeholder('—')
                            ->columnSpanFull(),
                    ]),

                Section::make('Status')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('status')
                            ->badge()
                            ->formatStateUsing(fn (AssetStatus $state): string => $state->label())
                            ->color(fn (AssetStatus $state): string => $state->color()),
                        TextEntry::make('assignedTo.name')
                            ->label('Assigned to')
                            ->placeholder('Unassigned'),
                        TextEntry::make('notes')
                            ->placeholder('—')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
