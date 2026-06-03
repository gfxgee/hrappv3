<?php

namespace App\Filament\Resources\OverTimeRequests\Pages;

use App\Filament\Resources\OverTimeRequests\OverTimeRequestResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListOverTimeRequests extends ListRecords
{
    protected static string $resource = OverTimeRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
