<?php

namespace App\Filament\Pages;

use App\Enum\AttendanceStatus;
use App\Enum\LeaveType;
use App\Filament\Support\EnhanceReason;
use App\Filament\Support\TimeSelect;
use App\Models\LeaveRequest;
use App\Services\LeaveCreditService;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @property-read Schema $form
 */
class FileLeaveRequest extends Page implements HasTable
{
    use InteractsWithTable;

    protected string $view = 'filament.pages.file-leave-request';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDateRange;

    protected static ?string $title = 'Leave Request Form';

    protected static ?string $navigationLabel = 'File Leave';

    protected static string|\UnitEnum|null $navigationGroup = 'My Workspace';

    protected static ?int $navigationSort = 2;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components(static::leaveFormFields())
            ->columns(2)
            ->statePath('data');
    }

    /**
     * Form fields shared between the filing form and the row Edit modal.
     *
     * @return array<Component>
     */
    public static function leaveFormFields(): array
    {
        return [
            Select::make('request_type')
                ->options(collect(LeaveType::all())->mapWithKeys(
                    fn (LeaveType $type): array => [$type->value => $type->label()],
                )->all())
                ->required(),
            Textarea::make('reason')
                ->required()
                ->hintActions(EnhanceReason::for('leave'))
                ->columnSpanFull(),
            DatePicker::make('start_date')
                ->required()
                ->default(today())
                ->live()
                ->afterStateUpdated(fn ($state, Set $set) => $set('end_date', $state)),
            DatePicker::make('end_date')
                ->required()
                ->default(today())
                ->afterOrEqual('start_date')
                ->rule(static fn (Get $get, ?Model $record): Closure => static function (
                    string $attribute,
                    mixed $value,
                    Closure $fail,
                ) use ($get, $record): void {
                    self::validateCreditBalance($get, $record, $fail);
                }),
            TimeSelect::make('start_time', '10:00'),
            TimeSelect::make('end_time', '18:00'),
        ];
    }

    /**
     * Fail validation when the requested leave exceeds the employee's
     * remaining credit for the chosen (tracked) leave type.
     */
    protected static function validateCreditBalance(Get $get, ?Model $record, Closure $fail): void
    {
        $user = auth()->user();
        $type = is_string($get('request_type')) ? LeaveType::tryFrom($get('request_type')) : null;

        if ($user === null || $type === null) {
            return;
        }

        $service = app(LeaveCreditService::class);
        $remaining = $service->remainingDays($user, $type, $record?->getKey());

        // Untracked types (WFH, LWOP) have no quota.
        if ($remaining === null) {
            return;
        }

        $requested = (new LeaveRequest)->forceFill([
            'start_date' => $get('start_date'),
            'end_date' => $get('end_date'),
            'start_time' => $get('start_time'),
            'end_time' => $get('end_time'),
        ])->durationInDays($service->workingHoursFor($user->userData), $service->holidayDates());

        if (round($requested, 2) > round($remaining, 2)) {
            $fail(sprintf(
                'This request needs %s day(s), but you only have %s day(s) of %s remaining.',
                rtrim(rtrim(number_format($requested, 2), '0'), '.'),
                rtrim(rtrim(number_format($remaining, 2), '0'), '.'),
                $type->plainLabel(),
            ));
        }
    }

    public function create(): void
    {
        $data = $this->form->getState();

        $data['user_id'] = auth()->id();
        $data['status'] = AttendanceStatus::FOR_APPROVAL->value;

        LeaveRequest::create($data);

        Notification::make()
            ->success()
            ->title('Leave request submitted')
            ->body('Your request has been sent for approval.')
            ->send();

        $this->form->fill();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => LeaveRequest::query()->where('user_id', auth()->id()))
            ->defaultSort('created_at', 'desc')
            ->columns([
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
                TextColumn::make('remarks')
                    ->placeholder('—')
                    ->wrap(),
                TextColumn::make('created_at')
                    ->label('Filed')
                    ->dateTime()
                    ->sortable(),
            ])
            ->recordActions([
                EditAction::make()
                    ->schema(static::leaveFormFields())
                    ->visible(fn (LeaveRequest $record): bool => $record->status === AttendanceStatus::FOR_APPROVAL),
                Action::make('cancel')
                    ->label('Cancel')
                    ->icon('heroicon-o-x-mark')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Cancel this leave request?')
                    ->modalDescription('The credit will be restored for tracked leave types.')
                    // Only pending requests can be cancelled; once approved it's locked.
                    ->visible(fn (LeaveRequest $record): bool => $record->status === AttendanceStatus::FOR_APPROVAL)
                    ->action(fn (LeaveRequest $record) => $record->update([
                        'status' => AttendanceStatus::CANCELLED->value,
                    ])),
            ])
            ->emptyStateHeading('No leave requests yet')
            ->emptyStateDescription('File your first leave request using the form above.');
    }

    /**
     * Leave credit balances for the authenticated user.
     *
     * Types without a quota column (e.g. WFH, LWOP) still appear so usage
     * is visible — they're returned with `tracked: false` and `total: null`.
     *
     * @return list<array{
     *     label: string,
     *     total: float|null,
     *     used: float,
     *     remaining: float|null,
     *     percent: float,
     *     color: string,
     *     tracked: bool,
     * }>
     */
    public function getLeaveCredits(): array
    {
        $user = auth()->user();
        $userData = $user?->userData;

        if ($userData === null) {
            return [];
        }

        $service = app(LeaveCreditService::class);
        $workingHours = $service->workingHoursFor($userData);
        $holidays = $service->holidayDates();

        // Sum the actual working-day duration of each active request, per type,
        // so weekends/holidays are free, a 1-hour leave costs a fraction, etc.
        $used = LeaveRequest::query()
            ->where('user_id', $user->id)
            ->whereNotIn('status', [AttendanceStatus::REJECTED->value, AttendanceStatus::CANCELLED->value])
            ->get(['request_type', 'start_date', 'end_date', 'start_time', 'end_time'])
            ->groupBy(fn (LeaveRequest $request): string => $request->request_type->value)
            ->map(fn ($group): float => $group->sum(
                fn (LeaveRequest $request): float => $request->durationInDays($workingHours, $holidays),
            ));

        // Display order for the credit cards.
        $types = [
            LeaveType::WFH,
            LeaveType::VACATION,
            LeaveType::SICK,
            LeaveType::EMERGENCY,
            LeaveType::BEREAVEMENT,
            LeaveType::MATERNITY,
            LeaveType::PATERNITY,
            LeaveType::LWOP,
        ];

        return collect($types)->map(function (LeaveType $leaveType) use ($userData, $used, $service): array {
            $column = $service->quotaColumn($leaveType);
            $consumed = round((float) ($used[$leaveType->value] ?? 0), 2);

            if ($column === null) {
                return [
                    'label' => $leaveType->label(),
                    'total' => null,
                    'used' => $consumed,
                    'remaining' => null,
                    'percent' => 0.0,
                    'color' => '#9ca3af',
                    'tracked' => false,
                ];
            }

            $total = (float) ($userData->{$column} ?? 0);
            $remaining = round(max($total - $consumed, 0), 2);
            $ratio = $total > 0 ? $remaining / $total : 0;

            return [
                'label' => $leaveType->label(),
                'total' => $total,
                'used' => $consumed,
                'remaining' => $remaining,
                'percent' => round($ratio * 100, 1),
                'color' => match (true) {
                    $total <= 0 => '#9ca3af',
                    $ratio >= 0.5 => '#16a34a',
                    $ratio >= 0.25 => '#d97706',
                    default => '#dc2626',
                },
                'tracked' => true,
            ];
        })->all();
    }
}
