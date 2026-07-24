<?php

namespace App\Filament\Resources\Users\Tables;

use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Support\Enums\FontFamily;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('photo')
                    ->disk('public')
                    ->circular()
                    ->defaultImageUrl(fn (User $record): string => 'https://ui-avatars.com/api/?name='.urlencode($record->name)),
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->label('Email address')
                    ->fontFamily(FontFamily::Mono)
                    ->searchable(),
                TextColumn::make('department.name')
                    ->label('Department')
                    ->badge()
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('job_title')
                    ->searchable()
                    ->wrap()
                    ->toggleable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'inactive' => 'gray',
                        default => 'warning',
                    }),
                IconColumn::make('active')
                    ->boolean(),
                TextColumn::make('birthday')
                    ->label('Birthday')
                    ->date()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('attendance_logs_count')
                    ->label('Attendance logs')
                    ->counts('attendanceLogs')
                    ->badge()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('bio_metric_id')
                    ->label('Biometric ID')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(['active' => 'Active', 'inactive' => 'Inactive']),
                TernaryFilter::make('active'),
                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                Action::make('impersonate')
                    ->label('Impersonate')
                    ->icon('heroicon-o-user-circle')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading(fn (User $record): string => "Impersonate {$record->name}?")
                    ->modalDescription('You will browse the app as this user until you choose to stop.')
                    // Super-admins only. Authorization is enforced server-side by the
                    // package controller (User::canImpersonate / canBeImpersonated);
                    // this only gates the button's visibility.
                    ->visible(fn (User $record): bool => auth()->user() instanceof User
                        && auth()->user()->canImpersonate()
                        && ! $record->is(auth()->user())
                        && $record->canBeImpersonated())
                    ->action(fn (User $record) => redirect()->to(route('impersonate', $record))),
                DeleteAction::make(),
                RestoreAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ])
            ->defaultSort('name', 'asc')
            ->defaultPaginationPageOption(50)
            ->defaultGroup('status');
    }
}
