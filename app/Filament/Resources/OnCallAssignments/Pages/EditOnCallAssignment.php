<?php

namespace App\Filament\Resources\OnCallAssignments\Pages;

use App\Filament\Resources\OnCallAssignments\OnCallAssignmentResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditOnCallAssignment extends EditRecord
{
    protected static string $resource = OnCallAssignmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->label('Reset to automatic')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->modalHeading('Reset this week to automatic?')
                ->modalDescription('The rotation will pick this week again based on roster order and availability.'),
        ];
    }

    /**
     * Editing a week by hand makes it an override, so the weekly job leaves it alone.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['is_override'] = true;

        return $data;
    }
}
