<?php

namespace App\Filament\Resources\Users\RelationManagers;

use App\Enum\AssignmentType;
use App\Filament\Resources\Assets\AssetResource;
use App\Models\AssetAssignment;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AssetAssignmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'assetAssignments';

    protected static ?string $title = 'Equipment';

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('assigned_at', 'desc')
            ->columns([
                TextColumn::make('asset.name')
                    ->label('Asset')
                    ->description(fn (AssetAssignment $record): ?string => $record->asset?->asset_tag)
                    ->url(fn (AssetAssignment $record): ?string => $record->asset_id
                        ? AssetResource::getUrl('view', ['record' => $record->asset_id])
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
            ]);
    }
}
