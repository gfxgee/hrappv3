<?php

namespace App\Filament\Resources\Holidays\Schemas;

use App\Enum\HolidayDuration;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class HolidayForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('emoji')
                    ->label('Emoji')
                    ->helperText('Optional. A single emoji shown next to the holiday, e.g. 🎄.')
                    ->maxLength(8),
                DatePicker::make('date')
                    ->required()
                    ->unique(ignoreRecord: true),
                Select::make('duration')
                    ->options(HolidayDuration::options())
                    ->default(HolidayDuration::FULL_DAY->value)
                    ->required(),
                Toggle::make('is_active')
                    ->label('Active')
                    ->helperText('Inactive holidays are hidden and not observed.')
                    ->default(true),
                RichEditor::make('description')
                    ->label('Description')
                    ->helperText('Optional. Add details and links to the source.')
                    ->toolbarButtons([
                        'bold', 'italic', 'underline', 'strike',
                        'bulletList', 'orderedList', 'link', 'blockquote', 'redo', 'undo',
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
