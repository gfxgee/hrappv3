<?php

namespace App\Filament\Pages;

use App\Enum\AttendanceStatus;
use App\Filament\Support\EnhanceReason;
use App\Models\OverTimeRequest;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Component;
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
class FileOverTimeRequest extends Page implements HasTable
{
    use InteractsWithTable;

    protected string $view = 'filament.pages.file-over-time-request';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static ?string $title = 'Overtime Request Form';

    protected static ?string $navigationLabel = 'File Overtime';

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
            ->components(static::overtimeFormFields())
            ->columns(2)
            ->statePath('data');
    }

    /**
     * Form fields shared between the filing form and the row Edit modal.
     *
     * @return array<Component>
     */
    public static function overtimeFormFields(): array
    {
        return [
            DatePicker::make('request_date')
                ->label('Overtime date')
                ->required()
                ->default(today()),
            TextInput::make('hours')
                ->required()
                ->numeric()
                ->minValue(0.5)
                ->maxValue(24)
                ->step(0.5)
                ->default(0.5)
                ->suffix('hrs'),
            Textarea::make('reason')
                ->required()
                ->hintActions(EnhanceReason::for('overtime'))
                ->columnSpanFull(),
        ];
    }

    public function create(): void
    {
        $data = $this->form->getState();

        $data['user_id'] = auth()->id();
        $data['status'] = AttendanceStatus::FOR_APPROVAL->value;

        OverTimeRequest::create($data);

        Notification::make()
            ->success()
            ->title('Overtime request submitted')
            ->body('Your request has been sent for approval.')
            ->send();

        $this->form->fill();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => OverTimeRequest::query()->where('user_id', auth()->id()))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('request_date')
                    ->label('Overtime date')
                    ->date()
                    ->sortable(),
                TextColumn::make('hours')
                    ->numeric(decimalPlaces: 2)
                    ->suffix(' hrs')
                    ->sortable(),
                TextColumn::make('reason')
                    ->limit(40)
                    ->tooltip(fn (OverTimeRequest $record): ?string => $record->reason),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (AttendanceStatus $state): string => $state->label())
                    ->color(fn (AttendanceStatus $state): string => $state->color()),
                TextColumn::make('approved_date')
                    ->label('Approved at')
                    ->dateTime()
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->label('Filed')
                    ->dateTime()
                    ->sortable(),
            ])
            ->recordActions([
                EditAction::make()
                    ->schema(static::overtimeFormFields())
                    ->visible(fn (OverTimeRequest $record): bool => $record->status === AttendanceStatus::FOR_APPROVAL),
                Action::make('cancel')
                    ->label('Cancel')
                    ->icon('heroicon-o-x-mark')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Cancel this overtime request?')
                    ->visible(fn (OverTimeRequest $record): bool => $record->status === AttendanceStatus::FOR_APPROVAL)
                    ->action(fn (OverTimeRequest $record) => $record->update([
                        'status' => AttendanceStatus::CANCELLED->value,
                    ])),
            ])
            ->emptyStateHeading('No overtime requests yet')
            ->emptyStateDescription('File your first overtime request using the form above.');
    }

    /**
     * Total approved overtime hours for the authenticated user this month.
     */
    public function getApprovedHoursThisMonth(): float
    {
        return (float) OverTimeRequest::query()
            ->where('user_id', auth()->id())
            ->where('status', AttendanceStatus::APPROVED->value)
            ->whereBetween('request_date', [now()->startOfMonth(), now()->endOfMonth()])
            ->sum('hours');
    }

    /**
     * Total hours pending approval for the authenticated user.
     */
    public function getPendingHours(): float
    {
        return (float) OverTimeRequest::query()
            ->where('user_id', auth()->id())
            ->where('status', AttendanceStatus::FOR_APPROVAL->value)
            ->sum('hours');
    }
}
