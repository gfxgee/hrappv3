<?php

namespace App\Filament\Resources\OverTimeRequests\Tables;

use App\Enum\AttendanceStatus;
use App\Models\OverTimeRequest;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Collection;

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
                BulkAction::make('approveSelected')
                    ->label('Approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->deselectRecordsAfterCompletion()
                    ->action(fn (Collection $records) => self::decideMany($records, AttendanceStatus::APPROVED)),
                BulkAction::make('rejectSelected')
                    ->label('Reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->deselectRecordsAfterCompletion()
                    ->action(fn (Collection $records) => self::decideMany($records, AttendanceStatus::REJECTED)),
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * Apply an approval decision to every still-pending record in the selection,
     * leaving already-decided ones untouched. Approving stamps the approved date.
     *
     * @param  Collection<int, OverTimeRequest>  $records
     */
    protected static function decideMany(Collection $records, AttendanceStatus $status): void
    {
        $pending = $records->filter(
            fn (OverTimeRequest $record): bool => $record->status === AttendanceStatus::FOR_APPROVAL,
        );

        $pending->each(fn (OverTimeRequest $record) => $record->update([
            'status' => $status->value,
            'approved_date' => $status === AttendanceStatus::APPROVED ? now() : null,
        ]));

        $verb = $status === AttendanceStatus::APPROVED ? 'approved' : 'rejected';
        $skipped = $records->count() - $pending->count();

        Notification::make()
            ->success()
            ->title("{$pending->count()} request(s) {$verb}")
            ->body($skipped > 0 ? "{$skipped} already decided and skipped." : null)
            ->send();
    }
}
