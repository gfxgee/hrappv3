<?php

namespace App\Filament\Resources\PraiseSessions\Pages;

use App\Filament\Resources\PraiseSessions\PraiseSessionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Width;

class ListPraiseSessions extends ListRecords
{
    protected static string $resource = PraiseSessionResource::class;
    protected Width|string|null $maxContentWidth = Width::Full;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
