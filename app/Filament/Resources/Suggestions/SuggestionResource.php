<?php

namespace App\Filament\Resources\Suggestions;

use App\Enum\SuggestionStatus;
use App\Filament\Resources\Suggestions\Pages\ListSuggestions;
use App\Filament\Resources\Suggestions\Tables\SuggestionsTable;
use App\Models\Suggestion;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class SuggestionResource extends Resource
{
    protected static ?string $model = Suggestion::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInbox;

    protected static string|UnitEnum|null $navigationGroup = 'HR Management';

    protected static ?int $navigationSort = 8;

    protected static ?string $navigationLabel = 'Suggestion Box';

    /** Roles permitted to read the anonymous suggestion box. */
    protected const MANAGER_ROLES = ['superadmin', 'super_admin', 'hr'];

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->hasAnyRole(static::MANAGER_ROLES);
    }

    /**
     * Suggestions are submitted anonymously through the employee Suggestion Box
     * page — never created or edited from the admin panel.
     */
    public static function canCreate(): bool
    {
        return false;
    }

    /**
     * Count of unreviewed suggestions, hidden when there are none.
     */
    public static function getNavigationBadge(): ?string
    {
        $count = Suggestion::query()->where('status', SuggestionStatus::NEW)->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function table(Table $table): Table
    {
        return SuggestionsTable::configure($table);
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
            'index' => ListSuggestions::route('/'),
        ];
    }
}
