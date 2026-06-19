<?php

namespace App\Filament\Resources\Announcements\Tables;

use App\Enum\AnnouncementType;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class AnnouncementsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('type')
                    ->badge()
                    ->formatStateUsing(fn (AnnouncementType $state): string => $state->label())
                    ->color(fn (AnnouncementType $state): string => $state->color()),
                TextColumn::make('title')
                    ->placeholder('—')
                    ->description(fn ($record): string => strip_tags((string) $record->message))
                    ->wrap()
                    ->searchable(),
                IconColumn::make('is_urgent')
                    ->label('Urgent')
                    ->boolean()
                    ->trueIcon('heroicon-o-exclamation-triangle')
                    ->trueColor('danger')
                    ->falseIcon('heroicon-o-minus')
                    ->falseColor('gray')
                    ->sortable(),
                ToggleColumn::make('is_active')
                    ->label('Active')
                    ->sortable(),
                TextColumn::make('departments.name')
                    ->label('Audience')
                    ->badge()
                    ->placeholder('Everyone')
                    ->toggleable(),
                TextColumn::make('starts_at')
                    ->label('From')
                    ->dateTime('M j, Y H:i')
                    ->placeholder('Immediately')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('ends_at')
                    ->label('Until')
                    ->dateTime('M j, Y H:i')
                    ->placeholder('No expiry')
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->options(AnnouncementType::toArray()),
                TernaryFilter::make('is_active')
                    ->label('Active'),
                TernaryFilter::make('is_urgent')
                    ->label('Urgent'),
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
