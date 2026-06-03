<?php

namespace App\Filament\Resources\LeaveRequests\Schemas;

use App\Enum\AttendanceStatus;
use App\Enum\LeaveType;
use App\Filament\Support\EnhanceReason;
use App\Filament\Support\TimeSelect;
use App\Models\User;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

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
                            ->default(fn () => User::query()->value('id'))
                            ->required(),
                        Select::make('request_type')
                            ->options(collect(LeaveType::all())->mapWithKeys(
                                fn (LeaveType $type): array => [$type->value => $type->label()],
                            )->all())
                            ->required(),
                        DatePicker::make('start_date')
                            ->required()
                            ->default(today())
                            ->live()
                            ->afterStateUpdated(fn ($state, Set $set) => $set('end_date', $state)),
                        DatePicker::make('end_date')
                            ->required()
                            ->default(today())
                            ->afterOrEqual('start_date'),
                        TimeSelect::make('start_time', '10:00'),
                        TimeSelect::make('end_time', '18:00'),
                        Textarea::make('reason')
                            ->hintActions(EnhanceReason::for('leave'))
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
}
