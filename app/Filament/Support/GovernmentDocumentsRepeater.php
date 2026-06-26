<?php

namespace App\Filament\Support;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;

class GovernmentDocumentsRepeater
{
    /**
     * A flexible list of {label, url} links to digital ID copies. Employees and
     * HR can add any number of documents (SSS, PhilHealth, Pag-IBIG, TIN, NBI…)
     * without a schema change per type — the rows are stored as JSON.
     */
    public static function make(string $name = 'government_documents'): Repeater
    {
        return Repeater::make($name)
            ->label('Government ID documents')
            ->addActionLabel('Add document')
            ->schema([
                TextInput::make('label')
                    ->placeholder('e.g. SSS, PhilHealth, Pag-IBIG, TIN, NBI')
                    ->required()
                    ->maxLength(255),
                TextInput::make('url')
                    ->label('Document link')
                    ->placeholder('https://drive.google.com/...')
                    ->url()
                    ->required()
                    ->maxLength(2048),
            ])
            ->columns(2)
            ->itemLabel(fn (array $state): ?string => $state['label'] ?? null)
            ->addable()
            ->deletable()
            ->reorderable(false)
            ->default([]);
    }
}
