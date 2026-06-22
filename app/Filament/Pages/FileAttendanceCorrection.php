<?php

namespace App\Filament\Pages;

use App\Enum\AttendanceCorrectionType;
use App\Enum\AttendanceStatus;
use App\Models\AttendanceCorrectionRequest;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * @property-read Schema $form
 */
class FileAttendanceCorrection extends Page implements HasTable
{
    use InteractsWithTable;

    protected string $view = 'filament.pages.file-attendance-correction';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPencilSquare;

    protected static ?string $title = 'Attendance Correction Form';

    protected static ?string $navigationLabel = 'File Attendance Correction';

    protected static string|\UnitEnum|null $navigationGroup = 'My Workspace';

    protected static ?int $navigationSort = 4;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components(static::correctionFormFields())
            ->columns(2)
            ->statePath('data');
    }

    /**
     * Form fields shared between the filing form and the row Edit modal.
     *
     * @return array<Component>
     */
    public static function correctionFormFields(): array
    {
        return [
            Select::make('correction_type')
                ->label('What needs correcting?')
                ->options(AttendanceCorrectionType::toArray())
                ->required()
                ->live()
                ->native(false),
            Select::make('target_log_type')
                ->label('Which punch is wrong?')
                ->options(['clockin' => 'Clock-in', 'clockout' => 'Clock-out'])
                ->native(false)
                ->visible(fn (Get $get): bool => $get('correction_type') === AttendanceCorrectionType::WRONG_TIME->value)
                ->required(fn (Get $get): bool => $get('correction_type') === AttendanceCorrectionType::WRONG_TIME->value),
            DateTimePicker::make('corrected_at')
                ->label('Correct date & time')
                ->helperText('The date and time your attendance should reflect.')
                ->seconds(false)
                ->required()
                ->default(now()),
            Textarea::make('reason')
                ->required()
                ->placeholder('Explain what happened (e.g. "Forgot to clock out after my shift").')
                ->columnSpanFull(),
        ];
    }

    public function create(): void
    {
        $data = $this->form->getState();

        $data['user_id'] = auth()->id();
        $data['status'] = AttendanceStatus::FOR_APPROVAL->value;

        AttendanceCorrectionRequest::create($data);

        Notification::make()
            ->success()
            ->title('Correction request submitted')
            ->body('Your request has been sent to HR for approval.')
            ->send();

        $this->form->fill();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => AttendanceCorrectionRequest::query()->where('user_id', auth()->id()))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('correction_type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn (AttendanceCorrectionType $state): string => $state->label()),
                TextColumn::make('corrected_at')
                    ->label('Correct time')
                    ->dateTime('M j, Y H:i')
                    ->sortable(),
                TextColumn::make('reason')
                    ->limit(40)
                    ->tooltip(fn (AttendanceCorrectionRequest $record): ?string => $record->reason),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (AttendanceStatus $state): string => $state->label())
                    ->color(fn (AttendanceStatus $state): string => $state->color()),
                TextColumn::make('remarks')
                    ->label('HR remarks')
                    ->placeholder('—')
                    ->wrap(),
                TextColumn::make('created_at')
                    ->label('Filed')
                    ->dateTime()
                    ->sortable(),
            ])
            ->recordActions([
                EditAction::make()
                    ->schema(static::correctionFormFields())
                    ->visible(fn (AttendanceCorrectionRequest $record): bool => $record->status === AttendanceStatus::FOR_APPROVAL),
                Action::make('cancel')
                    ->label('Cancel')
                    ->icon('heroicon-o-x-mark')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Cancel this correction request?')
                    ->visible(fn (AttendanceCorrectionRequest $record): bool => $record->status === AttendanceStatus::FOR_APPROVAL)
                    ->action(fn (AttendanceCorrectionRequest $record) => $record->update([
                        'status' => AttendanceStatus::CANCELLED->value,
                    ])),
            ])
            ->emptyStateHeading('No correction requests yet')
            ->emptyStateDescription('File a correction using the form above if a punch is missing or wrong.');
    }
}
