<?php

namespace App\Filament\Resources\PraiseSessions\Pages;

use App\Filament\Resources\PraiseSessions\PraiseSessionResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPraiseSession extends EditRecord
{
    protected static string $resource = PraiseSessionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
