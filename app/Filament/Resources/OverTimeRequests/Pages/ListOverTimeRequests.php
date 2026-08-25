<?php

namespace App\Filament\Resources\OverTimeRequests\Pages;

use App\Filament\Resources\OverTimeRequests\OverTimeRequestResource;
use App\Models\OverTimeRequest;
use App\Support\RequestCsvExport;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListOverTimeRequests extends ListRecords
{
    protected static string $resource = OverTimeRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')
                ->label('Export CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action(fn (): StreamedResponse => $this->exportCsv()),
            CreateAction::make(),
        ];
    }

    /**
     * Stream the currently filtered & sorted overtime requests as a CSV, so the
     * export always matches exactly what's shown in the table. Uses the shared
     * request layout so leave, overtime, and corrections all export alike.
     */
    public function exportCsv(): StreamedResponse
    {
        $query = $this->getFilteredSortedTableQuery();

        return RequestCsvExport::stream(
            'overtime-requests-'.now()->format('Ymd_His').'.csv',
            function (callable $write) use ($query): void {
                $query->with('user')->lazy()->each(
                    fn (OverTimeRequest $request) => $write(RequestCsvExport::fromOvertime($request)),
                );
            },
        );
    }
}
