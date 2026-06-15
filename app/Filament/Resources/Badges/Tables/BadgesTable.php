<?php

namespace App\Filament\Resources\Badges\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class BadgesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('label')
            ->columns([
                TextColumn::make('icon')
                    ->label(''),
                TextColumn::make('label')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('color')
                    ->badge()
                    ->color(fn (string $state): string => $state),
                TextColumn::make('points')
                    ->numeric()
                    ->sortable()
                    ->badge()
                    ->color('gray'),
                TextColumn::make('praises_count')
                    ->label('Times awarded')
                    ->counts('praises')
                    ->badge(),
                ToggleColumn::make('is_active')
                    ->label('Active'),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
