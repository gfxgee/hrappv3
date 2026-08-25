<?php

namespace App\Filament\Resources\AttendanceCorrectionRequests\Pages;

use App\Filament\Resources\AttendanceCorrectionRequests\AttendanceCorrectionRequestResource;
use App\Models\AttendanceCorrectionRequest;
use App\Support\RequestCsvExport;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListAttendanceCorrectionRequests extends ListRecords
{
    protected static string $resource = AttendanceCorrectionRequestResource::class;

    protected function getHeaderActions(): array
    {
        // No create action: requests are filed by employees on their own page.
        return [
            Action::make('export')
                ->label('Export CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action(fn (): StreamedResponse => $this->exportCsv()),
        ];
    }

    /**
     * Stream the currently filtered & sorted correction requests as a CSV, so
     * the export always matches exactly what's shown in the table. Uses the
     * shared request layout so leave, overtime, and corrections export alike.
     */
    public function exportCsv(): StreamedResponse
    {
        $query = $this->getFilteredSortedTableQuery();

        return RequestCsvExport::stream(
            'attendance-corrections-'.now()->format('Ymd_His').'.csv',
            function (callable $write) use ($query): void {
                $query->with('user')->lazy()->each(
                    fn (AttendanceCorrectionRequest $request) => $write(RequestCsvExport::fromCorrection($request)),
                );
            },
        );
    }
}
