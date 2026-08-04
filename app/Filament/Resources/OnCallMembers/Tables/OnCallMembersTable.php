<?php

namespace App\Filament\Resources\OnCallMembers\Tables;

use App\Models\OnCallAssignment;
use App\Models\OnCallMember;
use App\Services\OnCallService;
use Carbon\CarbonInterface;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class OnCallMembersTable
{
    public static function configure(Table $table): Table
    {
        $service = app(OnCallService::class);

        // The user on-call this week — highlighted in the roster.
        $currentUserId = $service->assignmentForWeek(today())?->user_id;

        // The next scheduled on-call week per member (user id => week start).
        $nextByUser = $service->nextOnCallWeekByUser();

        return $table
            ->reorderable('position')
            ->defaultSort('position')
            ->columns([
                TextColumn::make('position')
                    ->label('#')
                    ->rowIndex(),
                TextColumn::make('user.name')
                    ->label('Developer')
                    ->searchable()
                    ->weight('medium'),
                IconColumn::make('on_call_this_week')
                    ->label('On-call this week')
                    ->state(fn ($record): bool => $record->user_id === $currentUserId)
                    ->boolean(),
                TextColumn::make('next_on_call')
                    ->label('Next on-call')
                    ->badge()
                    ->color(fn ($record): string => $record->user_id === $currentUserId ? 'success' : 'gray')
                    ->state(function ($record) use ($nextByUser, $currentUserId): string {
                        $week = $nextByUser[$record->user_id] ?? null;

                        if ($week === null) {
                            return '—';
                        }

                        $range = $week->format('M j').' – '.$week->endOfWeek(CarbonInterface::SUNDAY)->format('M j');

                        return $record->user_id === $currentUserId ? "This week ({$range})" : $range;
                    }),
            ])
            ->recordActions([
                Action::make('assignThisWeek')
                    ->label('Make on-call this week')
                    ->icon('heroicon-o-phone-arrow-up-right')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading(fn (OnCallMember $record): string => "Put {$record->user?->name} on-call this week?")
                    ->modalDescription('This overrides the automatic rotation for this week only. Later weeks re-balance themselves.')
                    ->visible(fn (OnCallMember $record): bool => $record->user_id !== $currentUserId)
                    ->action(function (OnCallMember $record) use ($service): void {
                        OnCallAssignment::updateOrCreate(
                            ['week_start' => $service->weekStart(today())],
                            ['user_id' => $record->user_id, 'is_override' => true],
                        );

                        Notification::make()
                            ->success()
                            ->title("{$record->user?->name} is now on-call this week")
                            ->send();
                    }),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('No developers in the rotation yet')
            ->emptyStateDescription('Add developers, then drag to set the weekly order.');
    }
}
