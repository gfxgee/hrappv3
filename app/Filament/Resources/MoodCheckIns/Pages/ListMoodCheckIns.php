<?php

namespace App\Filament\Resources\MoodCheckIns\Pages;

use App\Filament\Resources\MoodCheckIns\MoodCheckInResource;
use Filament\Resources\Pages\ListRecords;

class ListMoodCheckIns extends ListRecords
{
    protected static string $resource = MoodCheckInResource::class;

    protected function getHeaderActions(): array
    {
        // No create action: check-ins come from employees on the dashboard.
        return [];
    }
}
