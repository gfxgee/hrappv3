<?php

namespace App\Filament\Resources\AttendanceCorrectionRequests\Tables;

use App\Enum\AttendanceCorrectionType;
use App\Enum\AttendanceStatus;
use App\Filament\Resources\Users\UserResource;
use App\Models\AttendanceCorrectionRequest;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class AttendanceCorrectionRequestsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('user.name')
                    ->label('Employee')
                    ->url(fn (AttendanceCorrectionRequest $record): ?string => $record->user_id
                        ? UserResource::getUrl('view', ['record' => $record->user_id])
                        : null)
                    ->color('primary')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('correction_type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn (AttendanceCorrectionType $state): string => $state->label()),
                TextColumn::make('punch')
                    ->label('Punch')
                    ->state(fn (AttendanceCorrectionRequest $record): string => match ($record->resolveTargetLogType()) {
                        'clockin' => 'Clock-in',
                        'clockout' => 'Clock-out',
                        default => '—',
                    })
                    ->toggleable(),
                TextColumn::make('corrected_at')
                    ->label('Correct time')
                    ->dateTime('M j, Y H:i')
                    ->sortable(),
                TextColumn::make('reason')
                    ->wrap(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (AttendanceStatus $state): string => $state->label())
                    ->color(fn (AttendanceStatus $state): string => $state->color()),
                TextColumn::make('remarks')
                    ->label('HR remarks')
                    ->placeholder('—')
                    ->wrap()
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->label('Filed')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(AttendanceStatus::toArray()),
                SelectFilter::make('correction_type')
                    ->label('Type')
                    ->options(AttendanceCorrectionType::toArray()),
            ])
            ->recordActions([
                Action::make('approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->modalHeading('Approve correction request')
                    ->modalDescription('Approving will update the employee\'s attendance log automatically (except "Other", which you handle manually).')
                    ->schema([
                        Textarea::make('remarks')
                            ->label('Remarks (optional)')
                            ->placeholder('Note for the employee, e.g. "Applied to your log."'),
                    ])
                    ->visible(fn (AttendanceCorrectionRequest $record): bool => $record->status === AttendanceStatus::FOR_APPROVAL)
                    ->action(fn (AttendanceCorrectionRequest $record, array $data) => $record->update([
                        'status' => AttendanceStatus::APPROVED->value,
                        'remarks' => $data['remarks'] ?? null,
                    ])),
                Action::make('reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->modalHeading('Reject correction request')
                    ->schema([
                        Textarea::make('remarks')
                            ->label('Reason for rejection')
                            ->required(),
                    ])
                    ->visible(fn (AttendanceCorrectionRequest $record): bool => $record->status === AttendanceStatus::FOR_APPROVAL)
                    ->action(fn (AttendanceCorrectionRequest $record, array $data) => $record->update([
                        'status' => AttendanceStatus::REJECTED->value,
                        'remarks' => $data['remarks'],
                    ])),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('No correction requests')
            ->emptyStateDescription('Employee attendance correction requests will appear here for review.');
    }
}
