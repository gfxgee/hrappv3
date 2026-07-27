<?php

namespace App\Filament\Resources\OnCallMembers\Pages;

use App\Filament\Resources\OnCallMembers\OnCallMemberResource;
use App\Models\OnCallMember;
use Filament\Resources\Pages\CreateRecord;

class CreateOnCallMember extends CreateRecord
{
    protected static string $resource = OnCallMemberResource::class;

    /**
     * New members join at the end of the rotation order.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['position'] = (int) OnCallMember::max('position') + 1;

        return $data;
    }
}
