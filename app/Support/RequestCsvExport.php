<?php

namespace App\Support;

use App\Models\AttendanceCorrectionRequest;
use App\Models\LeaveRequest;
use App\Models\OverTimeRequest;
use App\Services\LeaveCreditService;
use Closure;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * One shared CSV shape for every employee request export — leave, overtime, and
 * attendance corrections — mirroring the legacy SharePoint list layout HR
 * already works with (ID, Created, Title, Date, Email, TimeStart, TimeEnd,
 * Hrs, Reason).
 *
 * The first nine columns reproduce the SharePoint order exactly. `EndDate`,
 * `Name`, and `Status` are appended afterwards so the familiar sequence is
 * untouched: the SharePoint lists were one row per day, while a request here can
 * span a range, so one row per request keeps `ID` unique and `Hrs` covers the
 * whole request — column totals stay correct.
 */
final class RequestCsvExport
{
    /**
     * @var list<string>
     */
    public const COLUMNS = [
        // Columns 1-9 match the legacy SharePoint list exactly, in order, so
        // HR's existing spreadsheets line up. The extras are appended after.
        'ID', 'Created', 'Title', 'Date', 'Email', 'TimeStart', 'TimeEnd', 'Hrs', 'Reason',
        'EndDate', 'Name', 'Status',
    ];

    /**
     * Stream a CSV with the shared header. The callback receives a writer that
     * takes one already-mapped row.
     */
    public static function stream(string $filename, Closure $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, self::COLUMNS);

            $rows(function (array $row) use ($handle): void {
                fputcsv($handle, $row);
            });

            fclose($handle);
        }, $filename);
    }

    /**
     * @return list<string|int|float|null>
     */
    public static function fromLeave(LeaveRequest $request): array
    {
        $credits = app(LeaveCreditService::class);
        $workingHours = $credits->workingHoursFor($request->user?->userData);
        $days = $request->durationInDays($workingHours, $credits->holidayDates());

        return [
            $request->id,
            $request->created_at?->format('Y-m-d H:i'),
            $request->request_type?->plainLabel(),
            $request->start_date?->format('Y-m-d'),
            $request->user?->email,
            $request->start_time,
            $request->end_time,
            round($days * $workingHours, 2),
            $request->reason,
            $request->end_date?->format('Y-m-d'),
            $request->user?->name,
            $request->status?->label(),
        ];
    }

    /**
     * @return list<string|int|float|null>
     */
    public static function fromOvertime(OverTimeRequest $request): array
    {
        return [
            $request->id,
            $request->created_at?->format('Y-m-d H:i'),
            'Overtime',
            $request->request_date?->format('Y-m-d'),
            $request->user?->email,
            null, // overtime is logged as a number of hours, not a time span
            null,
            round((float) $request->hours, 2),
            $request->reason,
            $request->request_date?->format('Y-m-d'),
            $request->user?->name,
            $request->status?->label(),
        ];
    }

    /**
     * @return list<string|int|float|null>
     */
    public static function fromCorrection(AttendanceCorrectionRequest $request): array
    {
        return [
            $request->id,
            $request->created_at?->format('Y-m-d H:i'),
            $request->correction_type?->label(),
            $request->corrected_at?->format('Y-m-d'),
            $request->user?->email,
            $request->corrected_at?->format('H:i'),
            null,
            null, // a correction adjusts a punch time; it has no hour value
            $request->reason,
            $request->corrected_at?->format('Y-m-d'),
            $request->user?->name,
            $request->status?->label(),
        ];
    }
}
