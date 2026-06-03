<?php

namespace App\Filament\Resources\OverTimeRequests\Pages;

use App\Filament\Resources\OverTimeRequests\OverTimeRequestResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditOverTimeRequest extends EditRecord
{
    protected static string $resource = OverTimeRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
