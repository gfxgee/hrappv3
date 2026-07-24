<?php

namespace App\Filament\Resources\LeaveRequests\Pages;

use App\Filament\Resources\LeaveRequests\LeaveRequestResource;
use App\Models\LeaveRequest;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Width;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListLeaveRequests extends ListRecords
{
    protected static string $resource = LeaveRequestResource::class;

    protected Width|string|null $maxContentWidth = Width::Full;

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
     * Stream the currently filtered & sorted leave requests as a CSV, so the
     * export always matches exactly what's shown in the table.
     */
    public function exportCsv(): StreamedResponse
    {
        $query = $this->getFilteredSortedTableQuery();

        $filename = 'leave-requests-'.now()->format('Ymd_His').'.csv';

        return response()->streamDownload(function () use ($query): void {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, ['Name', 'Email', 'Type', 'Reason', 'Start date', 'End date', 'Start time', 'End time', 'Status', 'Filed']);

            $query->with('user')->lazy()->each(function (LeaveRequest $request) use ($handle): void {
                fputcsv($handle, [
                    $request->user?->name,
                    $request->user?->email,
                    $request->request_type?->plainLabel(),
                    $request->reason,
                    $request->start_date?->format('Y-m-d'),
                    $request->end_date?->format('Y-m-d'),
                    $request->start_time,
                    $request->end_time,
                    $request->status->label(),
                    $request->created_at?->format('Y-m-d H:i'),
                ]);
            });

            fclose($handle);
        }, $filename);
    }
}
