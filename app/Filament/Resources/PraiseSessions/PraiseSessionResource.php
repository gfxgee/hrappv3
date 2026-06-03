<?php

namespace App\Filament\Resources\PraiseSessions;

use App\Filament\Resources\PraiseSessions\Pages\CreatePraiseSession;
use App\Filament\Resources\PraiseSessions\Pages\EditPraiseSession;
use App\Filament\Resources\PraiseSessions\Pages\ListPraiseSessions;
use App\Filament\Resources\PraiseSessions\Schemas\PraiseSessionForm;
use App\Filament\Resources\PraiseSessions\Tables\PraiseSessionsTable;
use App\Models\PraiseSession;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class PraiseSessionResource extends Resource
{
    protected static ?string $model = PraiseSession::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDateRange;

    protected static string|\UnitEnum|null $navigationGroup = 'Recognition';

    protected static ?string $navigationLabel = 'Award Cycles';

    protected static ?string $recordTitleAttribute = 'name';

    /** @var list<string> */
    protected const MANAGER_ROLES = ['superadmin', 'super_admin', 'hr'];

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user !== null && $user->hasAnyRole(static::MANAGER_ROLES);
    }

    public static function form(Schema $schema): Schema
    {
        return PraiseSessionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PraiseSessionsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\PraisesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPraiseSessions::route('/'),
            'create' => CreatePraiseSession::route('/create'),
            'edit' => EditPraiseSession::route('/{record}/edit'),
        ];
    }
}
