<?php

namespace App\Filament\Resources\MoodCheckIns\Tables;

use App\Enum\Mood;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MoodCheckInsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('logged_on', 'desc')
            ->columns([
                TextColumn::make('user.name')
                    ->label('Employee')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('user.department.name')
                    ->label('Department')
                    ->badge()
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('mood')
                    ->badge()
                    ->formatStateUsing(fn (Mood $state): string => $state->emoji().' '.$state->label())
                    ->color(fn (Mood $state): string => $state->color()),
                TextColumn::make('note')
                    ->placeholder('—')
                    ->wrap()
                    ->toggleable(),
                TextColumn::make('logged_on')
                    ->label('Date')
                    ->date('M j, Y')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('mood')
                    ->options(Mood::toArray()),
                Filter::make('today')
                    ->label('Today only')
                    ->query(fn (Builder $query): Builder => $query->whereDate('logged_on', today())),
                Filter::make('needs_attention')
                    ->label('Needs attention (sad / stressed / sick)')
                    ->query(fn (Builder $query): Builder => $query->whereIn('mood', [
                        Mood::SAD->value,
                        Mood::STRESSED->value,
                        Mood::SICK->value,
                    ])),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('No mood check-ins yet')
            ->emptyStateDescription('Employee mood check-ins from the dashboard will appear here.');
    }
}
