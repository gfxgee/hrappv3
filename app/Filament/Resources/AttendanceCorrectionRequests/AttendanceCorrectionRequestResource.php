<?php

namespace App\Filament\Resources\AttendanceCorrectionRequests;

use App\Enum\AttendanceStatus;
use App\Filament\Concerns\ScopesToLedDepartments;
use App\Filament\Resources\AttendanceCorrectionRequests\Pages\ListAttendanceCorrectionRequests;
use App\Filament\Resources\AttendanceCorrectionRequests\Tables\AttendanceCorrectionRequestsTable;
use App\Models\AttendanceCorrectionRequest;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class AttendanceCorrectionRequestResource extends Resource
{
    use ScopesToLedDepartments;

    protected static ?string $model = AttendanceCorrectionRequest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPencilSquare;

    protected static string|UnitEnum|null $navigationGroup = 'HR Management';

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'Attendance Corrections';

    /** Roles with full access to every employee's correction requests. */
    protected const MANAGER_ROLES = ['superadmin', 'super_admin', 'hr'];

    /**
     * Full-access managers, or any team leader (who sees only their
     * department's requests via {@see ScopesToLedDepartments}).
     */
    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user !== null
            && ($user->hasAnyRole(static::MANAGER_ROLES) || $user->isTeamLeader());
    }

    /**
     * Requests are filed by employees on their own page — never created from
     * the admin panel.
     */
    public static function canCreate(): bool
    {
        return false;
    }

    /**
     * Count of requests awaiting a decision, scoped to what this user manages.
     */
    public static function getNavigationBadge(): ?string
    {
        $count = static::getEloquentQuery()
            ->where('status', AttendanceStatus::FOR_APPROVAL->value)
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function table(Table $table): Table
    {
        return AttendanceCorrectionRequestsTable::configure($table);
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
            'index' => ListAttendanceCorrectionRequests::route('/'),
        ];
    }
}
