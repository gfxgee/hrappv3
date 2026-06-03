<?php

namespace App\Filament\Resources\LeaveRequests\Tables;

use App\Enum\AttendanceStatus;
use App\Enum\LeaveType;
use App\Models\LeaveRequest;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

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
                TextColumn::make('start_date')
                    ->date()
                    ->sortable(),
                TextColumn::make('end_date')
                    ->date()
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (AttendanceStatus $state): string => $state->label())
                    ->color(fn (AttendanceStatus $state): string => $state->color()),
                TextColumn::make('created_at')
                    ->label('Filed')
                    ->dateTime()
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
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
