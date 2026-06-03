<?php

namespace App\Filament\Widgets;

use App\Enum\AttendanceStatus;
use App\Enum\LeaveType;
use App\Models\LeaveRequest;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class OnLeaveTodayWidget extends TableWidget
{
    protected static ?string $heading = 'On Leave Today';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = -1;

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => LeaveRequest::query()
                ->with('user')
                ->whereDate('start_date', '<=', today())
                ->whereDate('end_date', '>=', today())
                ->whereNotIn('status', [
                    AttendanceStatus::REJECTED->value,
                    AttendanceStatus::CANCELLED->value,
                ])
                ->latest('start_date'))
            ->columns([
                TextColumn::make('user.name')
                    ->label('Name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('request_type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn (LeaveType $state): string => $state->label()),
                TextColumn::make('reason')
                    ->wrap()
                    ->limit(60),
                TextColumn::make('start_time')
                    ->label('Start time')
                    ->placeholder('—'),
                TextColumn::make('end_time')
                    ->label('End time')
                    ->placeholder('—'),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (AttendanceStatus $state): string => $state->label())
                    ->color(fn (AttendanceStatus $state): string => $state->color()),
            ])
            ->emptyStateHeading('No one is on leave today')
            ->emptyStateIcon('heroicon-o-check-circle')
            ->paginated([10, 25, 50]);
    }
}
