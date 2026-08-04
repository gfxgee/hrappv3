<?php

namespace App\Filament\Resources\OnCallAssignments\Pages;

use App\Filament\Resources\OnCallAssignments\OnCallAssignmentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListOnCallAssignments extends ListRecords
{
    protected static string $resource = OnCallAssignmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
