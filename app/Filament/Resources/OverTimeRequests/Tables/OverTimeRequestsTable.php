<?php

namespace App\Filament\Resources\OverTimeRequests\Tables;

use App\Enum\AttendanceStatus;
use App\Models\OverTimeRequest;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class OverTimeRequestsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('user.name')
                    ->label('Employee')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('request_date')
                    ->label('Overtime date')
                    ->date()
                    ->sortable(),
                TextColumn::make('hours')
                    ->numeric(decimalPlaces: 2)
                    ->suffix(' hrs')
                    ->sortable(),
                TextColumn::make('reason')
                    ->limit(40)
                    ->tooltip(fn (OverTimeRequest $record): ?string => $record->reason),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (AttendanceStatus $state): string => $state->label())
                    ->color(fn (AttendanceStatus $state): string => $state->color()),
                TextColumn::make('approved_date')
                    ->label('Approved at')
                    ->dateTime()
                    ->placeholder('—')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->label('Filed')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(AttendanceStatus::toArray()),
            ])
            ->recordActions([
                Action::make('approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (OverTimeRequest $record): bool => $record->status === AttendanceStatus::FOR_APPROVAL)
                    ->action(fn (OverTimeRequest $record) => $record->update([
                        'status' => AttendanceStatus::APPROVED->value,
                        'approved_date' => now(),
                    ])),
                Action::make('reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (OverTimeRequest $record): bool => $record->status === AttendanceStatus::FOR_APPROVAL)
                    ->action(fn (OverTimeRequest $record) => $record->update([
                        'status' => AttendanceStatus::REJECTED->value,
                    ])),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
