<?php

namespace App\Filament\Pages;

use App\Models\Suggestion;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * Lets any employee submit a suggestion anonymously. The submission stores no
 * reference to the author, and there is intentionally no list of past
 * submissions here — nothing on this page can be traced back to a person.
 *
 * @property-read Schema $form
 */
class SuggestionBox extends Page
{
    protected string $view = 'filament.pages.suggestion-box';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInbox;

    protected static ?string $title = 'Suggestion Box';

    protected static ?string $navigationLabel = 'Suggestion Box';

    protected static string|UnitEnum|null $navigationGroup = 'My Workspace';

    protected static ?int $navigationSort = 5;

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
            ->components([
                Select::make('category')
                    ->options(array_combine(Suggestion::CATEGORIES, Suggestion::CATEGORIES))
                    ->placeholder('Select a category (optional)')
                    ->native(false),
                Textarea::make('message')
                    ->label('Your suggestion')
                    ->required()
                    ->rows(6)
                    ->maxLength(5000)
                    ->helperText('Please be respectful and constructive. Do not include your name if you wish to stay anonymous.'),
            ])
            ->statePath('data');
    }

    public function submit(): void
    {
        $data = $this->form->getState();

        // Store only the suggestion itself — never the authenticated user.
        Suggestion::create([
            'category' => $data['category'] ?? null,
            'message' => $data['message'],
        ]);

        Notification::make()
            ->success()
            ->title('Suggestion submitted')
            ->body('Thank you! Your suggestion was sent anonymously to HR.')
            ->send();

        $this->form->fill();
    }
}
