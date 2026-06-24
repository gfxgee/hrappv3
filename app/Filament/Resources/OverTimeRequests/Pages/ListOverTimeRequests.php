<?php

namespace App\Filament\Resources\OverTimeRequests\Pages;

use App\Filament\Resources\OverTimeRequests\OverTimeRequestResource;
use App\Models\OverTimeRequest;
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
     * export always matches exactly what's shown in the table.
     */
    public function exportCsv(): StreamedResponse
    {
        $query = $this->getFilteredSortedTableQuery();

        $filename = 'overtime-requests-'.now()->format('Ymd_His').'.csv';

        return response()->streamDownload(function () use ($query): void {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, ['Name', 'Email', 'Date', 'Hours', 'Reason', 'Status', 'Approved at', 'Filed']);

            $query->with('user')->lazy()->each(function (OverTimeRequest $request) use ($handle): void {
                fputcsv($handle, [
                    $request->user?->name,
                    $request->user?->email,
                    $request->request_date?->format('Y-m-d'),
                    $request->hours,
                    $request->reason,
                    $request->status->label(),
                    $request->approved_date?->format('Y-m-d H:i'),
                    $request->created_at?->format('Y-m-d H:i'),
                ]);
            });

            fclose($handle);
        }, $filename);
    }
}
