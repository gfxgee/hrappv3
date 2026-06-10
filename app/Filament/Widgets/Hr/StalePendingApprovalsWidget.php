<?php

namespace App\Filament\Widgets\Hr;

use App\Enum\AttendanceStatus;
use App\Enum\LeaveType;
use App\Filament\Resources\LeaveRequests\LeaveRequestResource;
use App\Filament\Resources\OverTimeRequests\OverTimeRequestResource;
use App\Models\LeaveRequest;
use App\Models\OverTimeRequest;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Collection;

/**
 * Leave and overtime requests still awaiting approval after 48 hours,
 * oldest first, with a direct link to review each one.
 */
class StalePendingApprovalsWidget extends TableWidget
{
    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = -1;

    /** Hours a pending request may wait before it counts as stale. */
    public const STALE_HOURS = 48;

    public static function canView(): bool
    {
        return (bool) auth()->user()?->isManager();
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('⚠️ Pending approvals > '.self::STALE_HOURS.' hrs')
            ->records(fn (): Collection => $this->staleRequests())
            ->emptyStateHeading('Nothing waiting')
            ->emptyStateDescription('No requests have been pending for more than '.self::STALE_HOURS.' hours.')
            ->paginated(false)
            ->columns([
                TextColumn::make('employee')
                    ->label('Employee'),
                TextColumn::make('type')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'Leave' ? 'info' : 'warning'),
                TextColumn::make('detail')
                    ->label('Request'),
                TextColumn::make('leaders')
                    ->label('Manager'),
                TextColumn::make('age_days')
                    ->label('Age')
                    ->formatStateUsing(fn (int $state): string => $state.' d')
                    ->color('danger'),
            ])
            ->recordActions([
                Action::make('review')
                    ->label('Review')
                    ->link()
                    ->url(fn (array $record): string => $record['url']),
            ]);
    }

    /**
     * @return Collection<string, array{employee: string, type: string, detail: string, leaders: string, age_days: int, url: string}>
     */
    protected function staleRequests(): Collection
    {
        $cutoff = now()->subHours(self::STALE_HOURS);

        $leaves = LeaveRequest::query()
            ->with('user.department.leaders')
            ->where('status', AttendanceStatus::FOR_APPROVAL->value)
            ->where('created_at', '<', $cutoff)
            ->get()
            ->map(fn (LeaveRequest $leave): array => [
                'key' => 'leave-'.$leave->id,
                'employee' => $leave->user?->name ?? '—',
                'type' => 'Leave',
                'detail' => $this->leaveLabel($leave->request_type),
                'leaders' => $leave->user?->department?->leaders->pluck('name')->implode(', ') ?: '—',
                'age_days' => (int) round($leave->created_at->diffInHours(now()) / 24),
                'url' => LeaveRequestResource::getUrl('edit', ['record' => $leave]),
            ]);

        $overtimes = OverTimeRequest::query()
            ->with('user.department.leaders')
            ->where('status', AttendanceStatus::FOR_APPROVAL->value)
            ->where('created_at', '<', $cutoff)
            ->get()
            ->map(fn (OverTimeRequest $request): array => [
                'key' => 'ot-'.$request->id,
                'employee' => $request->user?->name ?? '—',
                'type' => 'Overtime',
                'detail' => rtrim(rtrim(number_format((float) $request->hours, 1), '0'), '.').' h on '.$request->request_date?->format('j M'),
                'leaders' => $request->user?->department?->leaders->pluck('name')->implode(', ') ?: '—',
                'age_days' => (int) round($request->created_at->diffInHours(now()) / 24),
                'url' => OverTimeRequestResource::getUrl('edit', ['record' => $request]),
            ]);

        return $leaves->concat($overtimes)
            ->sortByDesc('age_days')
            ->keyBy('key');
    }

    /** Leave-type label tolerant of legacy rows with an empty type. */
    protected function leaveLabel(?LeaveType $type): string
    {
        if ($type === null || $type === LeaveType::EMPTY) {
            return 'Leave';
        }

        return $type->label();
    }
}
