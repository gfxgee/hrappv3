<?php

namespace App\Filament\Resources\MoodCheckIns;

use App\Filament\Resources\MoodCheckIns\Pages\ListMoodCheckIns;
use App\Filament\Resources\MoodCheckIns\Tables\MoodCheckInsTable;
use App\Models\MoodCheckIn;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class MoodCheckInResource extends Resource
{
    protected static ?string $model = MoodCheckIn::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFaceSmile;

    protected static string|UnitEnum|null $navigationGroup = 'HR Management';

    protected static ?int $navigationSort = 9;

    protected static ?string $navigationLabel = 'Mood Check-ins';

    /** Roles permitted to read employee mood check-ins. */
    protected const MANAGER_ROLES = ['superadmin', 'super_admin', 'hr'];

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->hasAnyRole(static::MANAGER_ROLES);
    }

    /**
     * Mood check-ins are submitted by employees on the dashboard — never
     * created from the admin panel.
     */
    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return MoodCheckInsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMoodCheckIns::route('/'),
        ];
    }
}
