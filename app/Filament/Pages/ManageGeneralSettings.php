<?php

namespace App\Filament\Pages;

use App\Settings\GeneralSettings;
use BackedEnum;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Pages\SettingsPage;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * Company-wide settings, editable by HR / super admins. Backed by the
 * App\Settings\GeneralSettings spatie settings class.
 */
class ManageGeneralSettings extends SettingsPage
{
    protected static string $settings = GeneralSettings::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Settings';

    protected static ?string $title = 'Settings';

    /** Roles allowed to view and edit company-wide settings. */
    protected const MANAGER_ROLES = ['superadmin', 'super_admin', 'hr'];

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->hasAnyRole(static::MANAGER_ROLES);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Tabs::make()->columnSpanFull()->tabs([
                Tab::make('Attendance')
                    ->icon(Heroicon::OutlinedClock)
                    ->schema([
                        TextInput::make('lunchHours')
                            ->label('Lunch break')
                            ->helperText('Hours deducted once a shift exceeds the threshold below.')
                            ->numeric()->minValue(0)->maxValue(8)->step(0.25)->suffix('hours')->required(),
                        TextInput::make('lunchThresholdHours')
                            ->label('Lunch threshold')
                            ->helperText('Deduct lunch only when the gross worked span exceeds this many hours.')
                            ->numeric()->minValue(0)->maxValue(24)->step(0.25)->suffix('hours')->required(),
                        TextInput::make('standardWorkingHours')
                            ->label('Standard working hours / day')
                            ->helperText('A full working day, used when computing leave credits.')
                            ->numeric()->minValue(1)->maxValue(24)->step(0.25)->suffix('hours')->required(),
                        CheckboxList::make('workingDays')
                            ->label('Working days')
                            ->helperText('Days that count for DTR and leave. Others are treated as rest days.')
                            ->options([
                                1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday',
                                5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday',
                            ])
                            ->columns(2)
                            ->required(),
                    ]),
                Tab::make('Dashboard')
                    ->icon(Heroicon::OutlinedRectangleGroup)
                    ->schema([
                        TextInput::make('birthdayWindowDays')
                            ->label('Upcoming birthdays window')
                            ->numeric()->minValue(1)->maxValue(366)->suffix('days')->required(),
                        TextInput::make('holidayWindowDays')
                            ->label('Upcoming holidays window')
                            ->numeric()->minValue(1)->maxValue(366)->suffix('days')->required(),
                        TextInput::make('leaveWindowDays')
                            ->label('Upcoming leaves window')
                            ->numeric()->minValue(1)->maxValue(366)->suffix('days')->required(),
                    ]),
                Tab::make('Import')
                    ->icon(Heroicon::OutlinedArrowUpTray)
                    ->schema([
                        TextInput::make('biometricDedupeMinutes')
                            ->label('Collapse double-punches within')
                            ->helperText('Default window for the biometric import (can be overridden per upload).')
                            ->numeric()->minValue(0)->maxValue(120)->suffix('minutes')->required(),
                    ]),
                Tab::make('Recognition')
                    ->icon(Heroicon::OutlinedHeart)
                    ->schema([
                        TextInput::make('praiseGifPerPage')
                            ->label('GIFs per page')
                            ->helperText('How many GIFs load per page in the praise comment picker.')
                            ->numeric()->minValue(1)->maxValue(50)->required(),
                    ]),
            ]),
        ]);
    }

    public function canEdit(): bool
    {
        return static::canAccess();
    }
}
