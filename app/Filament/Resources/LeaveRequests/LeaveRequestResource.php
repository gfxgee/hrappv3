<?php

namespace App\Filament\Resources\LeaveRequests;

use App\Enum\AttendanceStatus;
use App\Filament\Concerns\ScopesToLedDepartments;
use App\Filament\Resources\LeaveRequests\Pages\CreateLeaveRequest;
use App\Filament\Resources\LeaveRequests\Pages\EditLeaveRequest;
use App\Filament\Resources\LeaveRequests\Pages\ListLeaveRequests;
use App\Filament\Resources\LeaveRequests\Schemas\LeaveRequestForm;
use App\Filament\Resources\LeaveRequests\Tables\LeaveRequestsTable;
use App\Models\LeaveRequest;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class LeaveRequestResource extends Resource
{
    use ScopesToLedDepartments;

    protected static ?string $model = LeaveRequest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    /**
     * Number of requests awaiting approval (scoped to what this user manages).
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

    /**
     * The `request_type` column is cast to an enum, so we override the title
     * resolver to return a human-readable string instead of the raw attribute.
     */
    public static function getRecordTitle(?Model $record): ?string
    {
        if (! $record instanceof LeaveRequest) {
            return static::getModelLabel();
        }

        return trim(($record->user?->name ?? 'Employee').' — '.$record->request_type?->label());
    }

    /**
     * Roles permitted to manage every employee's leave requests.
     *
     * @var list<string>
     */
    protected const MANAGER_ROLES = ['superadmin', 'super_admin', 'hr'];

    /**
     * Full-access managers, or any team leader (i.e. anyone who leads at least
     * one department). Team leadership is derived solely from the departments a
     * user leads — there is no separate role to assign.
     */
    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user !== null
            && ($user->hasAnyRole(static::MANAGER_ROLES) || $user->isTeamLeader());
    }

    public static function form(Schema $schema): Schema
    {
        return LeaveRequestForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return LeaveRequestsTable::configure($table);
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
            'index' => ListLeaveRequests::route('/'),
            'create' => CreateLeaveRequest::route('/create'),
            'edit' => EditLeaveRequest::route('/{record}/edit'),
        ];
    }
}
