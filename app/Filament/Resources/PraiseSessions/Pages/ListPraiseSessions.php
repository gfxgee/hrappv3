<?php

namespace App\Filament\Resources\PraiseSessions\Pages;

use App\Filament\Resources\PraiseSessions\PraiseSessionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPraiseSessions extends ListRecords
{
    protected static string $resource = PraiseSessionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
