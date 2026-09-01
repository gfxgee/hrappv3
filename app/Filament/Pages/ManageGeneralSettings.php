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

/**
 * Company-wide settings, editable by HR / super admins. Backed by the
 * App\Settings\GeneralSettings spatie settings class.
 */
class ManageGeneralSettings extends SettingsPage
{
    protected static string $settings = GeneralSettings::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?string $title = 'Settings';

    /** Roles allowed to view and edit company-wide settings. */
    protected const MANAGER_ROLES = ['superadmin', 'super_admin', 'hr'];

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->hasAnyRole(static::MANAGER_ROLES);
    }

    // Reached from the user (avatar) menu, not the sidebar.
    public static function shouldRegisterNavigation(): bool
    {
        return false;
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
                        TextInput::make('lateGraceMinutes')
                            ->label('Late grace period')
                            ->helperText('Deducted from any tardiness on the DTR — arriving 44 minutes late with a 15-minute grace records 29 minutes.')
                            ->numeric()->minValue(0)->maxValue(120)->suffix('minutes')->required(),
                        TextInput::make('standardWorkingHours')
                            ->label('Standard working hours / day')
                            ->helperText('A full working day, used when computing leave credits.')
                            ->numeric()->minValue(1)->maxValue(24)->step(0.25)->suffix('hours')->required(),
                        TextInput::make('maxOvertimeHours')
                            ->label('Maximum overtime / request')
                            ->helperText('The most overtime an employee may file on a single request.')
                            ->numeric()->minValue(0.5)->maxValue(24)->step(0.5)->suffix('hours')->required(),
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
                        TextInput::make('comingUpWindowDays')
                            ->label('Coming up window')
                            ->helperText('How far ahead the dashboard\'s "Coming up" list looks for birthdays, anniversaries, and holidays. The HR Overview always shows the next 7 days.')
                            ->numeric()->minValue(1)->maxValue(366)->suffix('days')->required(),
                    ]),
                Tab::make('Biometric')
                    ->icon(Heroicon::OutlinedFingerPrint)
                    ->schema([
                        TextInput::make('biometricDedupeMinutes')
                            ->label('Collapse double-punches within')
                            ->helperText('Window for collapsing accidental repeated scans from the biometric scanner.')
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
