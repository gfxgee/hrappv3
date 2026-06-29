<?php

namespace App\Filament\Resources\Users\RelationManagers;

use App\Models\AttendanceLog;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
                TextColumn::make('exeral_id')
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
                Filter::make('logged_between')
                    ->schema([
                        DatePicker::make('from')->label('From'),
                        DatePicker::make('until')->label('Until'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $q, $date): Builder => $q->whereDate('created_at', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $q, $date): Builder => $q->whereDate('created_at', '<=', $date)))
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];

                        if (! empty($data['from'])) {
                            $indicators[] = 'From '.Carbon::parse($data['from'])->toFormattedDateString();
                        }

                        if (! empty($data['until'])) {
                            $indicators[] = 'Until '.Carbon::parse($data['until'])->toFormattedDateString();
                        }

                        return $indicators;
                    }),
            ])
            ->headerActions([
                Action::make('export')
                    ->label('Export CSV')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->action(fn (RelationManager $livewire): StreamedResponse => $this->exportCsv($livewire)),
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

    /**
     * Stream the currently filtered & sorted attendance logs as a CSV.
     * With no filters applied it exports every log for this employee.
     */
    protected function exportCsv(RelationManager $livewire): StreamedResponse
    {
        $query = $livewire->getFilteredSortedTableQuery();

        $filename = 'attendance-logs-'.now()->format('Ymd_His').'.csv';

        return response()->streamDownload(function () use ($query): void {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, ['Name', 'Email', 'Type', 'Device', 'Remarks', 'Logged at']);

            $query->with('user')->lazy()->each(function (AttendanceLog $log) use ($handle): void {
                fputcsv($handle, [
                    $log->user?->name,
                    $log->user?->email,
                    $log->type,
                    $log->device,
                    $log->remarks,
                    $log->created_at?->format('Y-m-d H:i'),
                ]);
            });

            fclose($handle);
        }, $filename);
    }
}
