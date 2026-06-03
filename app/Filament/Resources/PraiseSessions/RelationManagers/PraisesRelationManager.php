<?php

namespace App\Filament\Resources\PraiseSessions\RelationManagers;

use App\Models\Praise;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PraisesRelationManager extends RelationManager
{
    protected static string $relationship = 'praises';

    protected static ?string $title = 'Praises in this cycle';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('message')
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('recipient.name')
                    ->label('Recipient')
                    ->searchable(),
                TextColumn::make('sender.name')
                    ->label('From')
                    ->formatStateUsing(fn (?string $state, Praise $record): string => $state ?? $record->senderName()),
                TextColumn::make('badge.label')
                    ->label('Badge')
                    ->badge()
                    ->placeholder('—'),
                TextColumn::make('message')
                    ->limit(60)
                    ->wrap(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->emptyStateHeading('No praises in this cycle');
    }
}
