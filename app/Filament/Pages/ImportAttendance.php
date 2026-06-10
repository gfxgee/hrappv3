<?php

namespace App\Filament\Pages;

use App\Services\BiometricImportService;
use App\Settings\GeneralSettings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * Upload a biometric punch export, review and trim the inferred clock-in /
 * clock-out pairs, then commit them to attendance_logs.
 *
 * The parsed preview lives in component state (keyed by row key) and is
 * rendered as a custom-data table so reviewers can edit times, delete bad
 * rows, and drop unmatched employees before writing anything to the database.
 */
class ImportAttendance extends Page implements HasTable
{
    use InteractsWithTable;

    protected string $view = 'filament.pages.import-attendance';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowUpTray;

    protected static ?string $navigationLabel = 'Import Attendance';

    protected static ?string $title = 'Import Attendance';

    /** Roles allowed to import attendance. */
    protected const MANAGER_ROLES = ['superadmin', 'super_admin', 'hr'];

    /**
     * Reviewable preview rows, keyed by their row key.
     *
     * @var array<string, array{
     *     key: string, bio_metric_id: ?int, employee_name: ?string, user_id: ?int,
     *     date: string, time_in: ?string, time_out: ?string, punch_count: int, status: string,
     * }>
     */
    public array $previewRows = [];

    /** Minutes within which repeated scans are collapsed as double-punches. */
    public int $dedupeMinutes = BiometricImportService::DEFAULT_DEDUPE_MINUTES;

    public function mount(): void
    {
        $this->dedupeMinutes = app(GeneralSettings::class)->biometricDedupeMinutes;
    }

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->hasAnyRole(static::MANAGER_ROLES);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    /**
     * Page-level header actions: upload a file and commit the reviewed rows.
     *
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('upload')
                ->label('Upload export')
                ->icon(Heroicon::OutlinedArrowUpTray)
                ->schema([
                    FileUpload::make('file')
                        ->label('Biometric export file')
                        ->helperText('CSV or modern .xlsx. Legacy .xls exports must be re-saved as CSV/XLSX first.')
                        ->acceptedFileTypes([
                            'text/csv',
                            'text/plain',
                            'application/vnd.ms-excel',
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        ])
                        ->storeFiles(true)
                        ->disk('local')
                        ->directory('biometric-imports')
                        ->required(),
                    TextInput::make('dedupeMinutes')
                        ->label('Collapse double-punches within (minutes)')
                        ->numeric()
                        ->minValue(0)
                        ->default($this->dedupeMinutes),
                ])
                ->action(fn (array $data) => $this->parseUpload($data)),

            Action::make('commit')
                ->label('Commit to attendance logs')
                ->icon(Heroicon::OutlinedCheck)
                ->color('success')
                ->visible(fn (): bool => $this->previewRows !== [])
                ->requiresConfirmation()
                ->modalDescription('Insert the reviewed clock-in / clock-out rows into the attendance logs. Existing records with the same time are skipped.')
                ->action(fn () => $this->commit()),
        ];
    }

    /**
     * Parse the uploaded file into preview rows, then remove the temp file.
     *
     * @param  array{file: string, dedupeMinutes?: int|string|null}  $data
     */
    protected function parseUpload(array $data): void
    {
        $this->dedupeMinutes = (int) ($data['dedupeMinutes'] ?? $this->dedupeMinutes);

        $disk = Storage::disk('local');
        $path = $disk->path($data['file']);

        try {
            $service = app(BiometricImportService::class);
            $punches = $service->parse($path, pathinfo($data['file'], PATHINFO_EXTENSION));
            $skipped = $service->skippedDateRows;
            $rows = $service->buildPreview($punches, $this->dedupeMinutes);
        } catch (\RuntimeException $e) {
            Notification::make()->danger()->title('Could not read the file')->body($e->getMessage())->send();

            return;
        } finally {
            $disk->delete($data['file']);
        }

        if ($rows === []) {
            Notification::make()->warning()->title('No punches found')->body('The file parsed but contained no usable rows.')->send();

            return;
        }

        $this->previewRows = collect($rows)->keyBy('key')->all();
        $this->resetTable();

        $unmatched = collect($rows)->where('status', 'unmatched')->count();

        $notes = [];

        if ($unmatched > 0) {
            $notes[] = "{$unmatched} row(s) have an unmatched biometric ID — set the employee's bio_metric_id or remove them before committing.";
        }

        if ($skipped > 0) {
            $notes[] = "{$skipped} row(s) were skipped because their Date/Time could not be read.";
        }

        Notification::make()
            ->{$skipped > 0 ? 'warning' : 'success'}()
            ->title('Parsed '.count($rows).' day(s)')
            ->body($notes === [] ? 'Review the rows below, then commit.' : implode(' ', $notes))
            ->send();
    }

    protected function commit(): void
    {
        $service = app(BiometricImportService::class);
        $summary = $service->commit(array_values($this->previewRows));

        $this->previewRows = [];
        $this->resetTable();

        Notification::make()
            ->success()
            ->title('Import complete')
            ->body(sprintf(
                '%d clock-in(s), %d clock-out(s) created. %d already existed, %d unmatched skipped.',
                $summary['clock_ins'],
                $summary['clock_outs'],
                $summary['skipped_existing'],
                $summary['skipped_unmatched'],
            ))
            ->send();
    }

    public function table(Table $table): Table
    {
        return $table
            ->records(fn (): Collection => collect($this->previewRows))
            ->emptyStateHeading('Nothing to review yet')
            ->emptyStateDescription('Upload a biometric export to preview the clock-in / clock-out pairs.')
            ->columns([
                TextColumn::make('employee_name')
                    ->label('Employee')
                    ->description(fn (array $record): string => 'Bio ID: '.($record['bio_metric_id'] ?? '—')),
                TextColumn::make('date')
                    ->label('Date')
                    ->date(),
                TextColumn::make('time_in')
                    ->label('Time in')
                    ->formatStateUsing(fn (?string $state): string => $state ? Carbon::parse($state)->format('h:i A') : '—'),
                TextColumn::make('time_out')
                    ->label('Time out')
                    ->formatStateUsing(fn (?string $state): string => $state ? Carbon::parse($state)->format('h:i A') : '—'),
                TextColumn::make('punch_count')
                    ->label('Scans')
                    ->alignCenter(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'ok' => 'success',
                        'single_punch' => 'warning',
                        default => 'danger',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'ok' => 'Clock in & out',
                        'single_punch' => 'Single punch',
                        default => 'Unmatched ID',
                    }),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(['ok' => 'Clock in & out', 'single_punch' => 'Single punch', 'unmatched' => 'Unmatched ID']),
            ])
            ->recordActions([
                Action::make('edit')
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->iconButton()
                    ->fillForm(fn (array $record): array => [
                        'time_in' => $record['time_in'],
                        'time_out' => $record['time_out'],
                    ])
                    ->schema([
                        TextInput::make('time_in')->label('Time in')->placeholder('YYYY-MM-DD HH:MM:SS'),
                        TextInput::make('time_out')->label('Time out')->placeholder('YYYY-MM-DD HH:MM:SS'),
                    ])
                    ->action(fn (array $data, array $record) => $this->updateRow($record['key'], $data)),
                Action::make('delete')
                    ->icon(Heroicon::OutlinedTrash)
                    ->iconButton()
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(fn (array $record) => $this->deleteRow($record['key'])),
            ])
            ->toolbarActions([
                BulkAction::make('delete')
                    ->label('Delete selected')
                    ->icon(Heroicon::OutlinedTrash)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(fn (Collection $records) => $this->deleteRows($records->pluck('key')->all())),
            ]);
    }

    /**
     * @param  array{time_in?: ?string, time_out?: ?string}  $data
     */
    protected function updateRow(string $key, array $data): void
    {
        if (! isset($this->previewRows[$key])) {
            return;
        }

        $timeIn = filled($data['time_in'] ?? null) ? Carbon::parse($data['time_in'])->format('Y-m-d H:i:s') : null;
        $timeOut = filled($data['time_out'] ?? null) ? Carbon::parse($data['time_out'])->format('Y-m-d H:i:s') : null;

        $this->previewRows[$key]['time_in'] = $timeIn;
        $this->previewRows[$key]['time_out'] = $timeOut;
        $this->previewRows[$key]['status'] = $this->statusFor($this->previewRows[$key]['user_id'], $timeOut);

        $this->resetTable();
    }

    protected function statusFor(?int $userId, ?string $timeOut): string
    {
        return match (true) {
            $userId === null => 'unmatched',
            $timeOut === null => 'single_punch',
            default => 'ok',
        };
    }

    protected function deleteRow(string $key): void
    {
        unset($this->previewRows[$key]);
        $this->resetTable();
    }

    /**
     * @param  list<string>  $keys
     */
    protected function deleteRows(array $keys): void
    {
        foreach ($keys as $key) {
            unset($this->previewRows[$key]);
        }

        $this->resetTable();
    }
}
