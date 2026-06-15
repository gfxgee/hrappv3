<?php

namespace App\Filament\Resources\Announcements\Schemas;

use App\Enum\AnnouncementType;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class AnnouncementForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->helperText('Optional heading shown in bold on the banner.')
                    ->maxLength(255),
                Select::make('type')
                    ->options(AnnouncementType::toArray())
                    ->default(AnnouncementType::INFO->value)
                    ->required()
                    ->native(false),
                Toggle::make('is_active')
                    ->label('Active')
                    ->helperText('Inactive announcements never show, regardless of dates.')
                    ->default(true),
                DateTimePicker::make('starts_at')
                    ->label('Show from')
                    ->seconds(false)
                    ->helperText('Leave blank to show immediately.'),
                DateTimePicker::make('ends_at')
                    ->label('Show until')
                    ->seconds(false)
                    ->after('starts_at')
                    ->helperText('Leave blank for no expiry. Must be after "Show from".'),
                Select::make('departments')
                    ->relationship('departments', 'name')
                    ->multiple()
                    ->preload()
                    ->searchable()
                    ->helperText('Leave empty to show to everyone. Choose departments to target only their members.')
                    ->columnSpanFull(),
                RichEditor::make('message')
                    ->required()
                    ->toolbarButtons([
                        'bold', 'italic', 'underline', 'strike',
                        'bulletList', 'orderedList', 'link', 'redo', 'undo',
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
