<?php

namespace App\Filament\Resources\Users\RelationManagers;

use App\Enum\AttendanceStatus;
use App\Models\OverTimeRequest;
use Carbon\CarbonInterface;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OverTimeRequestsRelationManager extends RelationManager
{
    protected static string $relationship = 'overTimeRequests';

    protected static ?string $title = 'Overtime Requests';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                DatePicker::make('request_date')
                    ->label('Date')
                    ->required(),
                TextInput::make('hours')
                    ->numeric()
                    ->minValue(0.25)
                    ->maxValue(24)
                    ->step(0.25)
                    ->suffix('hours')
                    ->required(),
                Textarea::make('reason')
                    ->required()
                    ->maxLength(1000),
                Select::make('status')
                    ->options(AttendanceStatus::toArray())
                    ->default(AttendanceStatus::FOR_APPROVAL->value)
                    ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('reason')
            ->defaultSort('request_date', 'desc')
            // Apply filters as soon as a value changes — no "Apply" click needed.
            ->deferFilters(false)
            ->columns([
                TextColumn::make('request_date')
                    ->label('Date')
                    ->date('D, M j, Y')
                    ->sortable(),
                TextColumn::make('hours')
                    ->numeric(decimalPlaces: 2)
                    ->suffix(' h')
                    ->sortable()
                    ->summarize(Sum::make()->label('Total')->numeric(decimalPlaces: 2)->suffix(' h')),
                TextColumn::make('reason')
                    ->limit(40)
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (AttendanceStatus $state): string => $state->label())
                    ->color(fn (AttendanceStatus $state): string => $state->color()),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(AttendanceStatus::toArray()),
                Filter::make('requested_between')
                    ->schema([
                        DatePicker::make('from')->label('From'),
                        DatePicker::make('until')->label('Until'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $q, $date): Builder => $q->whereDate('request_date', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $q, $date): Builder => $q->whereDate('request_date', '<=', $date)))
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
                Action::make('thisMonth')
                    ->label('This month')
                    ->icon('heroicon-o-calendar')
                    ->color('gray')
                    ->action(fn (RelationManager $livewire) => $this->applyDateRange($livewire, now()->startOfMonth(), now()->endOfMonth())),
                Action::make('lastMonth')
                    ->label('Last month')
                    ->icon('heroicon-o-calendar')
                    ->color('gray')
                    ->action(fn (RelationManager $livewire) => $this->applyDateRange(
                        $livewire,
                        now()->subMonthNoOverflow()->startOfMonth(),
                        now()->subMonthNoOverflow()->endOfMonth(),
                    )),
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
     * Apply a from/until range to the date filter (used by the quick actions).
     *
     * Writes the deferred filter state and pushes it through Filament's own
     * applyTableFilters(), which syncs the live filter state, the filter form
     * UI, and the pagination — and works whether or not filters are deferred.
     */
    protected function applyDateRange(RelationManager $livewire, CarbonInterface $from, CarbonInterface $until): void
    {
        $range = [
            'from' => $from->toDateString(),
            'until' => $until->toDateString(),
        ];

        $livewire->tableDeferredFilters ??= [];
        $livewire->tableDeferredFilters['requested_between'] = $range;
        $livewire->tableFilters['requested_between'] = $range;

        $livewire->applyTableFilters();
    }

    /**
     * Stream the currently filtered & sorted overtime requests as a CSV.
     * With no filters applied it exports every request for this employee.
     */
    protected function exportCsv(RelationManager $livewire): StreamedResponse
    {
        $query = $livewire->getFilteredSortedTableQuery();

        $filename = 'overtime-requests-'.now()->format('Ymd_His').'.csv';

        return response()->streamDownload(function () use ($query): void {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, ['Name', 'Email', 'Date', 'Hours', 'Reason', 'Status']);

            $query->with('user')->lazy()->each(function (OverTimeRequest $request) use ($handle): void {
                fputcsv($handle, [
                    $request->user?->name,
                    $request->user?->email,
                    $request->request_date?->format('Y-m-d'),
                    $request->hours,
                    $request->reason,
                    $request->status->label(),
                ]);
            });

            fclose($handle);
        }, $filename);
    }
}
