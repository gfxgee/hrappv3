<?php

namespace App\Filament\Resources\OnCallMembers;

use App\Filament\Resources\OnCallMembers\Pages\CreateOnCallMember;
use App\Filament\Resources\OnCallMembers\Pages\EditOnCallMember;
use App\Filament\Resources\OnCallMembers\Pages\ListOnCallMembers;
use App\Filament\Resources\OnCallMembers\Schemas\OnCallMemberForm;
use App\Filament\Resources\OnCallMembers\Tables\OnCallMembersTable;
use App\Models\OnCallMember;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class OnCallMemberResource extends Resource
{
    protected static ?string $model = OnCallMember::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhoneArrowUpRight;

    protected static string|\UnitEnum|null $navigationGroup = 'HR Management';

    protected static ?string $navigationLabel = 'On-Call Rotation';

    protected static ?string $modelLabel = 'on-call developer';

    protected static ?int $navigationSort = 6;

    /**
     * Managing the on-call roster is restricted to HR/admin-level roles.
     */
    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->isManager();
    }

    public static function getRecordTitle(?Model $record): ?string
    {
        return $record instanceof OnCallMember ? $record->user?->name : null;
    }

    public static function form(Schema $schema): Schema
    {
        return OnCallMemberForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OnCallMembersTable::configure($table);
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
            'index' => ListOnCallMembers::route('/'),
            'create' => CreateOnCallMember::route('/create'),
            'edit' => EditOnCallMember::route('/{record}/edit'),
        ];
    }
}
