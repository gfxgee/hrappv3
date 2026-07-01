<?php

namespace App\Filament\Resources\Assets\RelationManagers;

use App\Enum\AssignmentType;
use App\Filament\Resources\Users\UserResource;
use App\Models\AssetAssignment;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AssignmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'assignments';

    protected static ?string $title = 'Assignment history';

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('assigned_at', 'desc')
            ->columns([
                TextColumn::make('user.name')
                    ->label('Holder')
                    ->url(fn (AssetAssignment $record): ?string => $record->user_id
                        ? UserResource::getUrl('view', ['record' => $record->user_id])
                        : null)
                    ->color('primary')
                    ->searchable(),
                TextColumn::make('type')
                    ->badge()
                    ->formatStateUsing(fn (AssignmentType $state): string => $state->label())
                    ->color(fn (AssignmentType $state): string => $state->color()),
                TextColumn::make('assigned_at')
                    ->label('Assigned')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('due_at')
                    ->label('Due')
                    ->date()
                    ->placeholder('—'),
                TextColumn::make('returned_at')
                    ->label('Returned')
                    ->dateTime()
                    ->placeholder('Still held'),
                IconColumn::make('open')
                    ->label('Open')
                    ->boolean()
                    ->state(fn (AssetAssignment $record): bool => $record->isOpen()),
                TextColumn::make('assignedBy.name')
                    ->label('Assigned by')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('notes')
                    ->wrap()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label('Type')
                    ->options(AssignmentType::toArray()),
                TernaryFilter::make('status')
                    ->label('Status')
                    ->placeholder('All')
                    ->trueLabel('Currently held')
                    ->falseLabel('Returned')
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereNull('returned_at'),
                        false: fn (Builder $query): Builder => $query->whereNotNull('returned_at'),
                        blank: fn (Builder $query): Builder => $query,
                    ),
            ]);
    }
}
