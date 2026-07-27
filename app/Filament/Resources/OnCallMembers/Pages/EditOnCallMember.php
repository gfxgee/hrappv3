<?php

namespace App\Filament\Resources\OnCallMembers\Pages;

use App\Filament\Resources\OnCallMembers\OnCallMemberResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditOnCallMember extends EditRecord
{
    protected static string $resource = OnCallMemberResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
