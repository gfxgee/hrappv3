<?php

namespace App\Filament\Resources\LeaveRequests\Pages;

use App\Filament\Resources\LeaveRequests\LeaveRequestResource;
use App\Models\LeaveRequest;
use App\Support\RequestCsvExport;
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
     * export always matches exactly what's shown in the table. Uses the shared
     * request layout so leave, overtime, and corrections all export alike.
     */
    public function exportCsv(): StreamedResponse
    {
        $query = $this->getFilteredSortedTableQuery();

        return RequestCsvExport::stream(
            'leave-requests-'.now()->format('Ymd_His').'.csv',
            function (callable $write) use ($query): void {
                $query->with('user.userData')->lazy()->each(
                    fn (LeaveRequest $request) => $write(RequestCsvExport::fromLeave($request)),
                );
            },
        );
    }
}
