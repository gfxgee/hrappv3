<?php

namespace App\Filament\Resources\AttendanceCorrectionRequests\Pages;

use App\Filament\Resources\AttendanceCorrectionRequests\AttendanceCorrectionRequestResource;
use Filament\Resources\Pages\ListRecords;

class ListAttendanceCorrectionRequests extends ListRecords
{
    protected static string $resource = AttendanceCorrectionRequestResource::class;

    protected function getHeaderActions(): array
    {
        // No create action: requests are filed by employees on their own page.
        return [];
    }
}
