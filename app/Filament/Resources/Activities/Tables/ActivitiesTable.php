<?php

namespace App\Filament\Resources\Activities\Tables;

use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;

class ActivitiesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label('When')
                    ->dateTime('M j, Y H:i')
                    ->sortable(),
                TextColumn::make('causer.name')
                    ->label('Actor')
                    ->placeholder('System / Guest')
                    ->searchable(),
                TextColumn::make('event')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => $state ? str($state)->headline()->toString() : '—')
                    ->color(fn (?string $state): string => match ($state) {
                        'created', 'login' => 'success',
                        'updated' => 'warning',
                        'deleted', 'failed_login' => 'danger',
                        'logout' => 'gray',
                        default => 'info',
                    }),
                TextColumn::make('log_name')
                    ->label('Log')
                    ->badge()
                    ->toggleable(),
                TextColumn::make('subject_type')
                    ->label('Subject')
                    ->formatStateUsing(fn (?string $state, Activity $record): string => $state
                        ? class_basename($state).' #'.$record->subject_id
                        : '—'),
                TextColumn::make('description')
                    ->wrap()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('event')
                    ->options([
                        'login' => 'Login',
                        'logout' => 'Logout',
                        'failed_login' => 'Failed login',
                        'created' => 'Created',
                        'updated' => 'Updated',
                        'deleted' => 'Deleted',
                    ]),
                SelectFilter::make('log_name')
                    ->label('Log')
                    ->options([
                        'hr' => 'HR records',
                        'auth' => 'Authentication',
                        'default' => 'Default',
                    ]),
                Filter::make('logged_between')
                    ->schema([
                        DatePicker::make('from')->label('From'),
                        DatePicker::make('until')->label('Until'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $q, $date): Builder => $q->whereDate('created_at', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $q, $date): Builder => $q->whereDate('created_at', '<=', $date)))
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];

                        if (! empty($data['from'])) {
                            $indicators[] = 'From '.Carbon::parse($data['from'])->toFormattedDateString();
                        }

                        if (! empty($data['until'])) {
                            $indicators[] = 'Until '.Carbon::parse($data['until'])->toFormattedDateString();
                        }

                        return $indicators;
                    }),
            ])
            ->recordActions([
                Action::make('view')
                    ->icon('heroicon-o-eye')
                    ->iconButton()
                    ->modalHeading('Activity detail')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->modalContent(fn (Activity $record): View => view('filament.activities.detail', ['activity' => $record])),
            ]);
    }
}
