<?php

namespace App\Filament\Resources\LeaveRequests\Schemas;

use App\Enum\AttendanceStatus;
use App\Enum\LeaveType;
use App\Filament\Support\EnhanceReason;
use App\Filament\Support\TimeSelect;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Services\LeaveCreditService;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

class LeaveRequestForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Request')
                    ->columns(2)
                    ->schema([
                        Select::make('user_id')
                            ->label('Employee')
                            ->relationship('user', 'name')
                            ->searchable()
                            ->preload()
                            ->live()
                            ->default(fn () => User::query()->value('id'))
                            ->required(),
                        Select::make('request_type')
                            ->options(collect(LeaveType::all())->mapWithKeys(
                                fn (LeaveType $type): array => [$type->value => $type->label()],
                            )->all())
                            ->live()
                            ->required(),
                        DatePicker::make('start_date')
                            ->required()
                            ->default(today())
                            ->live()
                            ->afterStateUpdated(fn ($state, Set $set) => $set('end_date', $state)),
                        DatePicker::make('end_date')
                            ->required()
                            ->default(today())
                            ->live()
                            ->afterOrEqual('start_date'),
                        TimeSelect::make('start_time', '10:00'),
                        TimeSelect::make('end_time', '18:00'),
                        Textarea::make('reason')
                            ->hintActions(EnhanceReason::for('leave'))
                            ->columnSpanFull(),
                        Placeholder::make('credit_balance')
                            ->label('Leave credits')
                            ->content(fn (Get $get, ?LeaveRequest $record): HtmlString => self::creditSummary($get, $record))
                            ->columnSpanFull(),
                    ]),

                Section::make('Approval')
                    ->columns(2)
                    ->schema([
                        Select::make('status')
                            ->options(AttendanceStatus::toArray())
                            ->default(AttendanceStatus::FOR_APPROVAL->value)
                            ->required(),
                        Textarea::make('remarks')
                            ->helperText('Visible to the employee.')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    /**
     * A live read-out of the employee's remaining credit for the chosen type
     * and year, flagging when the request would exceed it.
     *
     * HR is warned rather than blocked: recording an approved exception (or
     * correcting history) is a legitimate admin action. The employee-facing
     * forms still enforce the limit.
     */
    protected static function creditSummary(Get $get, ?LeaveRequest $record): HtmlString
    {
        $user = filled($get('user_id')) ? User::find($get('user_id')) : null;
        $type = is_string($get('request_type')) ? LeaveType::tryFrom($get('request_type')) : null;

        if ($user === null || $type === null) {
            return new HtmlString('<span class="text-gray-500">Pick an employee and leave type to see their balance.</span>');
        }

        $service = app(LeaveCreditService::class);
        $year = $service->balanceYearFor($get('start_date'));
        $remaining = $service->remainingDays($user, $type, $record?->getKey(), $year);

        if ($remaining === null) {
            return new HtmlString(sprintf(
                '<span class="text-gray-500">%s has no quota - nothing to deduct.</span>',
                e($type->plainLabel()),
            ));
        }

        $requested = (new LeaveRequest)->forceFill([
            'start_date' => $get('start_date'),
            'end_date' => $get('end_date'),
            'start_time' => $get('start_time'),
            'end_time' => $get('end_time'),
        ])->durationInDays($service->workingHoursFor($user->userData), $service->holidayDates());

        $summary = sprintf(
            '%s &middot; %s day(s) remaining for %d &middot; this request uses %s day(s).',
            e($type->plainLabel()),
            self::trimDays($remaining),
            $year,
            self::trimDays($requested),
        );

        if (round($requested, 2) > round($remaining, 2)) {
            return new HtmlString(sprintf(
                '<span class="font-medium text-danger-600 dark:text-danger-400">&#9888; Over by %s day(s).</span> <span class="text-gray-500">%s</span>',
                self::trimDays($requested - $remaining),
                $summary,
            ));
        }

        return new HtmlString('<span class="text-gray-500">'.$summary.'</span>');
    }

    /**
     * Format a day count without trailing zeros (1.50 -> 1.5, 2.00 -> 2).
     */
    protected static function trimDays(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2), '0'), '.');
    }
}
