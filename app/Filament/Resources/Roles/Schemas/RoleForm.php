<?php

namespace App\Filament\Resources\Roles\Schemas;

use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

class RoleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                        Hidden::make('guard_name')
                            ->default('web'),
                    ]),

                Section::make('Permissions')
                    ->description('Capabilities for this role. Most access in the app is governed by the role itself (e.g. HR, Office Manager); these flags cover the few permission-based checks.')
                    ->schema([
                        CheckboxList::make('permissions')
                            ->relationship('permissions', 'name')
                            ->getOptionLabelFromRecordUsing(fn (Permission $record): string => Str::headline($record->name))
                            ->searchable()
                            ->bulkToggleable()
                            ->columns(2)
                            ->noSearchResultsMessage('No permissions found.')
                            ->helperText('No permissions are listed until they are defined for the application.'),
                    ]),
            ]);
    }
}
