<?php

namespace App\Filament\Resources\LeaveRequests\Tables;

use App\Enum\AttendanceStatus;
use App\Enum\LeaveType;
use App\Models\LeaveRequest;
use Carbon\Carbon;
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

class LeaveRequestsTable
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
                TextColumn::make('request_type')
                    ->badge()
                    ->formatStateUsing(fn (LeaveType $state): string => $state->label()),
                TextColumn::make('reason')
                    ->wrap(),
                TextColumn::make('start_date')
                    ->date()
                    ->sortable(),
                TextColumn::make('end_date')
                    ->date()
                    ->sortable(),
                TextColumn::make('start_time'),
                TextColumn::make('end_time'),
                TextColumn::make('duration')
                    ->label('Total Hours')
                    ->state(function ($record) {
                        // 1. Handle missing data
                        if (! $record->start_time || ! $record->end_time) {
                            return '--';
                        }

                        // 2. Parse the time strings
                        $start = Carbon::parse($record->start_time);
                        $end = Carbon::parse($record->end_time);

                        // 3. Handle overnight shifts (e.g., 10 PM to 2 AM)
                        if ($end->lessThan($start)) {
                            $end->addDay();
                        }

                        // 4. Return formatted difference
                        // %h = hours, %i = minutes. Result example: "8h 30m"
                        return $start->diff($end)->format('%hh %im');
                    }),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (AttendanceStatus $state): string => $state->label())
                    ->color(fn (AttendanceStatus $state): string => $state->color()),
                TextColumn::make('created_at')
                    ->label('Filed')
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(AttendanceStatus::toArray()),
                SelectFilter::make('request_type')
                    ->options(collect(LeaveType::all())->mapWithKeys(
                        fn (LeaveType $type): array => [$type->value => $type->label()],
                    )->all()),
            ])
            ->recordActions([
                Action::make('approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (LeaveRequest $record): bool => $record->status === AttendanceStatus::FOR_APPROVAL)
                    ->action(fn (LeaveRequest $record) => $record->update(['status' => AttendanceStatus::APPROVED])),
                Action::make('reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (LeaveRequest $record): bool => $record->status === AttendanceStatus::FOR_APPROVAL)
                    ->action(fn (LeaveRequest $record) => $record->update(['status' => AttendanceStatus::REJECTED])),
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
     * leaving already-decided ones untouched.
     *
     * @param  Collection<int, LeaveRequest>  $records
     */
    protected static function decideMany(Collection $records, AttendanceStatus $status): void
    {
        $pending = $records->filter(
            fn (LeaveRequest $record): bool => $record->status === AttendanceStatus::FOR_APPROVAL,
        );

        $pending->each(fn (LeaveRequest $record) => $record->update(['status' => $status->value]));

        $verb = $status === AttendanceStatus::APPROVED ? 'approved' : 'rejected';
        $skipped = $records->count() - $pending->count();

        Notification::make()
            ->success()
            ->title("{$pending->count()} request(s) {$verb}")
            ->body($skipped > 0 ? "{$skipped} already decided and skipped." : null)
            ->send();
    }
}
