<?php

namespace App\Filament\Resources\OnCallAssignments\Pages;

use App\Filament\Resources\OnCallAssignments\OnCallAssignmentResource;
use Filament\Resources\Pages\CreateRecord;

class CreateOnCallAssignment extends CreateRecord
{
    protected static string $resource = OnCallAssignmentResource::class;

    /**
     * A week set by hand is an override, so the weekly job leaves it alone.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['is_override'] = true;

        return $data;
    }
}
