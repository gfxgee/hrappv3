<?php

namespace App\Filament\Resources\OverTimeRequests;

use App\Enum\AttendanceStatus;
use App\Filament\Concerns\ScopesToLedDepartments;
use App\Filament\Resources\OverTimeRequests\Pages\CreateOverTimeRequest;
use App\Filament\Resources\OverTimeRequests\Pages\EditOverTimeRequest;
use App\Filament\Resources\OverTimeRequests\Pages\ListOverTimeRequests;
use App\Filament\Resources\OverTimeRequests\Schemas\OverTimeRequestForm;
use App\Filament\Resources\OverTimeRequests\Tables\OverTimeRequestsTable;
use App\Models\OverTimeRequest;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class OverTimeRequestResource extends Resource
{
    use ScopesToLedDepartments;

    protected static ?string $model = OverTimeRequest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static ?string $navigationLabel = 'Overtime Requests';

    /**
     * Number of overtime requests awaiting approval (scoped to what this user manages).
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
     * Roles permitted to manage every employee's overtime requests.
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

    /**
     * Custom title resolver — the `reason` column can be long, so we combine
     * the employee name with the request date for a clearer page header.
     */
    public static function getRecordTitle(?Model $record): ?string
    {
        if (! $record instanceof OverTimeRequest) {
            return static::getModelLabel();
        }

        $when = $record->request_date?->format('M j, Y');

        return trim(($record->user?->name ?? 'Employee').($when ? ' — '.$when : ''));
    }

    public static function form(Schema $schema): Schema
    {
        return OverTimeRequestForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OverTimeRequestsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOverTimeRequests::route('/'),
            'create' => CreateOverTimeRequest::route('/create'),
            'edit' => EditOverTimeRequest::route('/{record}/edit'),
        ];
    }
}
