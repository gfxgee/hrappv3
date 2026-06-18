<?php

namespace App\Filament\Resources\Suggestions\Tables;

use App\Enum\SuggestionStatus;
use App\Models\Suggestion;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class SuggestionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('category')
                    ->badge()
                    ->placeholder('Uncategorized')
                    ->toggleable(),
                TextColumn::make('message')
                    ->wrap()
                    ->searchable(),
                SelectColumn::make('status')
                    ->options(SuggestionStatus::toArray())
                    ->selectablePlaceholder(false)
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Submitted')
                    ->dateTime('M j, Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(SuggestionStatus::toArray()),
                SelectFilter::make('category')
                    ->options(array_combine(Suggestion::CATEGORIES, Suggestion::CATEGORIES)),
            ])
            ->recordActions([
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('No suggestions yet')
            ->emptyStateDescription('Anonymous suggestions from employees will appear here.');
    }
}
