<?php

namespace App\Filament\Resources\OverTimeRequests\Tables;

use App\Enum\AttendanceStatus;
use App\Enum\UserStatus;
use App\Filament\Resources\Users\UserResource;
use App\Models\OverTimeRequest;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class OverTimeRequestsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            // Clicking a row opens a read-only view modal instead of jumping to edit.
            ->recordAction('view')
            ->recordUrl(null)
            // Only list overtime filed by active employees.
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->whereHas(
                'user',
                fn (Builder $userQuery): Builder => $userQuery->where('status', UserStatus::ACTIVE->value),
            ))
            ->columns([
                TextColumn::make('user.name')
                    ->label('Employee')
                    ->url(fn (OverTimeRequest $record): ?string => $record->user_id
                        ? UserResource::getUrl('view', ['record' => $record->user_id])
                        : null)
                    ->color('primary')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('request_date')
                    ->label('Overtime date')
                    ->date()
                    ->sortable(),
                TextColumn::make('hours')
                    ->numeric(decimalPlaces: 2)
                    ->suffix(' hrs')
                    ->sortable()
                    ->summarize([
                        Sum::make('forApprovalHours')
                            ->label('For approval')
                            ->query(fn (QueryBuilder $query): QueryBuilder => $query->where('status', AttendanceStatus::FOR_APPROVAL->value))
                            ->numeric(decimalPlaces: 2)
                            ->suffix(' hrs'),
                        Sum::make('approvedHours')
                            ->label('Approved')
                            ->query(fn (QueryBuilder $query): QueryBuilder => $query->whereIn('status', [
                                AttendanceStatus::APPROVED->value,
                                AttendanceStatus::APPROVED_AND_VERIFIED->value,
                            ]))
                            ->numeric(decimalPlaces: 2)
                            ->suffix(' hrs'),
                    ]),
                TextColumn::make('reason')
                    ->limit(40)
                    ->wrap()
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
                SelectFilter::make('user')
                    ->label('Employee')
                    ->relationship('user', 'name', fn (Builder $query): Builder => $query->where('status', UserStatus::ACTIVE->value)->orderBy('name'))
                    ->searchable()
                    ->preload(),
                SelectFilter::make('status')
                    ->options(AttendanceStatus::toArray()),
                Filter::make('requested_between')
                    ->schema([
                        DatePicker::make('from')->label('From'),
                        DatePicker::make('until')->label('Until'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $q, $date): Builder => $q->whereDate('request_date', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $q, $date): Builder => $q->whereDate('request_date', '<=', $date)))
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
                ViewAction::make(),
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
                Action::make('verify')
                    ->label('Verify')
                    ->icon('heroicon-o-shield-check')
                    ->color('primary')
                    ->requiresConfirmation()
                    ->modalHeading('Verify this overtime request?')
                    ->modalDescription('Final HR verification, recorded for payroll/records. This cannot be undone here.')
                    ->visible(fn (OverTimeRequest $record): bool => $record->status === AttendanceStatus::APPROVED && self::canVerify())
                    ->action(fn (OverTimeRequest $record) => $record->update([
                        'status' => AttendanceStatus::APPROVED_AND_VERIFIED->value,
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
                BulkAction::make('verifySelected')
                    ->label('Verify')
                    ->icon('heroicon-o-shield-check')
                    ->color('primary')
                    ->requiresConfirmation()
                    ->deselectRecordsAfterCompletion()
                    ->visible(fn (): bool => self::canVerify())
                    ->action(fn (Collection $records) => self::verifyMany($records)),
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * Only HR and super-admins may perform the final verification.
     */
    protected static function canVerify(): bool
    {
        return (bool) auth()->user()?->hasAnyRole(['superadmin', 'super_admin', 'hr']);
    }

    /**
     * Verify every approved record in the selection (final HR step),
     * skipping any that aren't approved yet.
     *
     * @param  Collection<int, OverTimeRequest>  $records
     */
    protected static function verifyMany(Collection $records): void
    {
        $approved = $records->filter(
            fn (OverTimeRequest $record): bool => $record->status === AttendanceStatus::APPROVED,
        );

        $approved->each(fn (OverTimeRequest $record) => $record->update([
            'status' => AttendanceStatus::APPROVED_AND_VERIFIED->value,
        ]));

        $skipped = $records->count() - $approved->count();

        Notification::make()
            ->success()
            ->title("{$approved->count()} request(s) verified")
            ->body($skipped > 0 ? "{$skipped} not yet approved and skipped." : null)
            ->send();
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
