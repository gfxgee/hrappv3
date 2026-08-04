<?php

namespace App\Filament\Resources\OnCallAssignments\Schemas;

use App\Models\OnCallMember;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;

class OnCallAssignmentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                DatePicker::make('week_start')
                    ->label('Week')
                    ->required()
                    ->default(fn (): string => CarbonImmutable::parse(today())
                        ->startOfWeek(CarbonInterface::MONDAY)
                        ->toDateString())
                    ->helperText("Pick any day in the week — it snaps to that week's Monday.")
                    // Weeks are keyed by their Monday, so normalise whatever day
                    // was picked before it is saved or uniqueness-checked.
                    ->dehydrateStateUsing(fn (?string $state): ?string => filled($state)
                        ? CarbonImmutable::parse($state)->startOfWeek(CarbonInterface::MONDAY)->toDateString()
                        : null)
                    ->unique(ignoreRecord: true)
                    ->validationMessages([
                        'unique' => 'That week already has an on-call developer — edit that week instead.',
                    ]),
                Select::make('user_id')
                    ->label('Developer')
                    ->options(fn (): array => User::query()
                        ->whereIn('id', OnCallMember::query()->pluck('user_id'))
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all())
                    ->searchable()
                    ->preload()
                    ->required()
                    ->helperText('Only developers on the on-call roster can be assigned.'),
            ]);
    }
}
