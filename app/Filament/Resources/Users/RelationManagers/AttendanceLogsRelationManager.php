<?php

namespace App\Filament\Resources\Users\RelationManagers;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class AttendanceLogsRelationManager extends RelationManager
{
    protected static string $relationship = 'attendanceLogs';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('type')
                    ->options(['clockin' => 'Clock in', 'clockout' => 'Clock out'])
                    ->required(),
                Select::make('device')
                    ->options(['web' => 'Web', 'biometric' => 'Biometric', 'mobile' => 'Mobile'])
                    ->default('web'),
                TextInput::make('remarks')
                    ->maxLength(255),
                DateTimePicker::make('created_at')
                    ->label('Logged at')
                    ->seconds(false),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('type')
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('type')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'clockin' ? 'success' : 'danger')
                    ->searchable(),
                TextColumn::make('device')
                    ->badge()
                    ->toggleable(),
                TextColumn::make('remarks')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->label('Logged at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->options(['clockin' => 'Clock in', 'clockout' => 'Clock out']),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
