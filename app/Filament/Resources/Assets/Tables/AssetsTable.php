<?php

namespace App\Filament\Resources\Assets\Tables;

use App\Enum\AssetCategory;
use App\Enum\AssetStatus;
use App\Enum\AssignmentType;
use App\Filament\Resources\Users\UserResource;
use App\Models\Asset;
use App\Models\User;
use App\Services\AssetAssignmentService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;

class AssetsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('asset_tag')
                    ->label('Tag')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('category')
                    ->badge()
                    ->formatStateUsing(fn (AssetCategory $state): string => $state->label())
                    ->sortable(),
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('brand')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('serial_number')
                    ->label('Serial')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (AssetStatus $state): string => $state->label())
                    ->color(fn (AssetStatus $state): string => $state->color())
                    ->sortable(),
                TextColumn::make('assignedTo.name')
                    ->label('Assigned to')
                    ->placeholder('—')
                    ->url(fn (Asset $record): ?string => $record->assigned_to
                        ? UserResource::getUrl('view', ['record' => $record->assigned_to])
                        : null)
                    ->color('primary')
                    ->searchable(),
                TextColumn::make('purchased_at')
                    ->label('Purchased')
                    ->date()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('category')
                    ->options(AssetCategory::toArray()),
                SelectFilter::make('status')
                    ->options(AssetStatus::toArray()),
                TrashedFilter::make(),
            ])
            ->recordActions([
                Action::make('assign')
                    ->label('Assign')
                    ->icon('heroicon-o-user-plus')
                    ->color('primary')
                    ->visible(fn (Asset $record): bool => $record->status === AssetStatus::AVAILABLE)
                    ->schema([
                        Select::make('user_id')
                            ->label('Assign to')
                            ->options(fn (): array => User::query()->active()->orderBy('name')->pluck('name', 'id')->all())
                            ->searchable()
                            ->required(),
                        Select::make('type')
                            ->label('Assignment type')
                            ->options(AssignmentType::toArray())
                            ->default(AssignmentType::PERMANENT->value)
                            ->live()
                            ->required(),
                        DateTimePicker::make('due_at')
                            ->label('Due back')
                            ->visible(fn (Get $get): bool => $get('type') === AssignmentType::BORROW->value)
                            ->required(fn (Get $get): bool => $get('type') === AssignmentType::BORROW->value),
                        Textarea::make('notes'),
                    ])
                    ->action(function (Asset $record, array $data): void {
                        app(AssetAssignmentService::class)->assign(
                            $record,
                            User::findOrFail($data['user_id']),
                            AssignmentType::from($data['type']),
                            isset($data['due_at']) && $data['due_at'] ? Carbon::parse($data['due_at']) : null,
                            $data['notes'] ?? null,
                            auth()->user(),
                        );

                        Notification::make()->title('Asset assigned')->success()->send();
                    }),
                Action::make('return')
                    ->label('Return')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('warning')
                    ->visible(fn (Asset $record): bool => in_array($record->status, [AssetStatus::ASSIGNED, AssetStatus::BORROWED], true))
                    ->schema([
                        Textarea::make('notes')
                            ->label('Return notes')
                            ->helperText('Optional — e.g. condition on return.'),
                    ])
                    ->requiresConfirmation()
                    ->action(function (Asset $record, array $data): void {
                        app(AssetAssignmentService::class)->return(
                            $record,
                            $data['notes'] ?? null,
                            auth()->user(),
                        );

                        Notification::make()->title('Asset returned')->success()->send();
                    }),
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
