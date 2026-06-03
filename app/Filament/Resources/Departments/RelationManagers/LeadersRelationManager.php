<?php

namespace App\Filament\Resources\Departments\RelationManagers;

use Filament\Actions\AttachAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DetachAction;
use Filament\Actions\DetachBulkAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class LeadersRelationManager extends RelationManager
{
    protected static string $relationship = 'leaders';

    // User's inverse of Department::leaders() — used to exclude already-attached users.
    protected static ?string $inverseRelationship = 'ledDepartments';

    protected static ?string $title = 'Team Leaders';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->label('Email address')
                    ->searchable(),
                TextColumn::make('job_title')
                    ->toggleable(),
            ])
            ->headerActions([
                AttachAction::make()
                    ->label('Add team leader')
                    ->recordSelectSearchColumns(['name', 'email'])
                    ->recordSelectOptionsQuery(fn (Builder $query): Builder => $query->active())
                    ->preloadRecordSelect(),
            ])
            ->recordActions([
                DetachAction::make()
                    ->label('Remove'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DetachBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('No team leaders yet')
            ->emptyStateDescription('Add an employee as a leader of this department.');
    }
}
