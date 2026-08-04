<?php

namespace App\Filament\Resources\OnCallAssignments;

use App\Filament\Resources\OnCallAssignments\Pages\CreateOnCallAssignment;
use App\Filament\Resources\OnCallAssignments\Pages\EditOnCallAssignment;
use App\Filament\Resources\OnCallAssignments\Pages\ListOnCallAssignments;
use App\Filament\Resources\OnCallAssignments\Schemas\OnCallAssignmentForm;
use App\Filament\Resources\OnCallAssignments\Tables\OnCallAssignmentsTable;
use App\Models\OnCallAssignment;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class OnCallAssignmentResource extends Resource
{
    protected static ?string $model = OnCallAssignment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|\UnitEnum|null $navigationGroup = 'HR Management';

    protected static ?string $navigationLabel = 'On-Call Schedule';

    protected static ?string $modelLabel = 'on-call week';

    protected static ?int $navigationSort = 7;

    /**
     * Scheduling on-call weeks is restricted to HR/admin-level roles.
     */
    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->isManager();
    }

    public static function form(Schema $schema): Schema
    {
        return OnCallAssignmentForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OnCallAssignmentsTable::configure($table);
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
            'index' => ListOnCallAssignments::route('/'),
            'create' => CreateOnCallAssignment::route('/create'),
            'edit' => EditOnCallAssignment::route('/{record}/edit'),
        ];
    }
}
