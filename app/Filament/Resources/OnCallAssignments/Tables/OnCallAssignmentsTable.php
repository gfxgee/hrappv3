<?php

namespace App\Filament\Resources\OnCallAssignments\Tables;

use App\Models\OnCallAssignment;
use App\Services\OnCallService;
use Carbon\CarbonInterface;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class OnCallAssignmentsTable
{
    public static function configure(Table $table): Table
    {
        $currentWeek = app(OnCallService::class)->weekStart(today())->toDateString();

        return $table
            ->defaultSort('week_start', 'desc')
            ->columns([
                TextColumn::make('week_start')
                    ->label('Week')
                    ->sortable()
                    ->formatStateUsing(fn ($state): string => $state->format('M j')
                        .' – '.$state->copy()->endOfWeek(CarbonInterface::SUNDAY)->format('M j, Y'))
                    ->description(fn (OnCallAssignment $record): ?string => $record->week_start->toDateString() === $currentWeek
                        ? 'This week'
                        : null),
                TextColumn::make('user.name')
                    ->label('Developer')
                    ->placeholder('Nobody available')
                    ->searchable()
                    ->weight('medium'),
                IconColumn::make('is_override')
                    ->label('Manually set')
                    ->boolean()
                    ->trueIcon('heroicon-o-hand-raised')
                    ->trueColor('warning')
                    ->falseIcon('heroicon-o-sparkles')
                    ->falseColor('gray')
                    ->tooltip(fn (OnCallAssignment $record): string => $record->is_override
                        ? 'Manually set — the weekly job will not change it'
                        : 'Chosen automatically by the rotation'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->label('Reset to automatic')
                    ->icon('heroicon-o-arrow-path')
                    ->color('gray')
                    ->modalHeading('Reset this week to automatic?')
                    ->modalDescription('The rotation will pick this week again based on roster order and availability.'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('No weeks scheduled yet')
            ->emptyStateDescription('Weeks appear here once the rotation runs, or when you set one manually.');
    }
}
