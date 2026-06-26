<?php

namespace App\Filament\Support;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;

class PcSpecificationsRepeater
{
    /**
     * Common workstation components offered as datalist suggestions. The field
     * still accepts any custom value, so new component types never need code.
     *
     * @var list<string>
     */
    public const COMPONENT_SUGGESTIONS = [
        'System Unit / CPU',
        'Processor',
        'Motherboard',
        'RAM',
        'Storage (SSD/HDD)',
        'GPU / Video Card',
        'Monitor',
        'Keyboard',
        'Mouse',
        'Headset',
        'Webcam',
        'UPS',
        'Laptop',
    ];

    /**
     * A flexible list of {component, details} rows describing an employee's
     * workstation. The component field suggests standard labels (Monitor, RAM…)
     * but accepts custom entries; details holds the brand/model/spec.
     */
    public static function make(string $name = 'pc_specifications'): Repeater
    {
        return Repeater::make($name)
            ->label('PC specifications')
            ->addActionLabel('Add component')
            ->schema([
                TextInput::make('component')
                    ->placeholder('e.g. RAM, Monitor, Mouse')
                    ->datalist(self::COMPONENT_SUGGESTIONS)
                    ->required()
                    ->maxLength(255),
                TextInput::make('details')
                    ->label('Brand / model / spec')
                    ->placeholder('e.g. Corsair Vengeance 8GB')
                    ->required()
                    ->maxLength(255),
            ])
            ->columns(2)
            ->itemLabel(fn (array $state): ?string => $state['component'] ?? null)
            ->collapsible()
            ->collapsed()
            ->addable()
            ->deletable()
            ->reorderable(false)
            ->default([]);
    }
}
