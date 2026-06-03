<?php

namespace App\Filament\Resources\OverTimeRequests\Schemas;

use App\Enum\AttendanceStatus;
use App\Filament\Support\EnhanceReason;
use App\Models\User;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class OverTimeRequestForm
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
                            ->required(),
                        DatePicker::make('request_date')
                            ->label('Overtime date')
                            ->required(),
                        TextInput::make('hours')
                            ->required()
                            ->numeric()
                            ->minValue(0.25)
                            ->maxValue(24)
                            ->step(0.25)
                            ->suffix('hrs'),
                        Textarea::make('reason')
                            ->required()
                            ->hintActions(EnhanceReason::for('overtime'))
                            ->columnSpanFull(),
                    ]),

                Section::make('Approval')
                    ->columns(2)
                    ->schema([
                        Select::make('status')
                            ->options(AttendanceStatus::toArray())
                            ->default(AttendanceStatus::FOR_APPROVAL->value)
                            ->required(),
                        DateTimePicker::make('approved_date')
                            ->label('Approved at'),
                    ]),
            ]);
    }
}
