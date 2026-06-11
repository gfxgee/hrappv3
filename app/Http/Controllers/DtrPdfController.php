<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\DtrService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\PdfBuilder;
use Symfony\Component\HttpFoundation\Response;

class DtrPdfController extends Controller
{
    /**
     * Stream a printable Daily Time Record PDF for the requested employee and
     * period. Authorization mirrors the on-screen DTR page.
     */
    public function __invoke(Request $request, DtrService $dtrService): PdfBuilder
    {
        $viewer = $request->user();

        abort_if($viewer === null, Response::HTTP_FORBIDDEN);

        $employee = User::query()->findOrFail((int) $request->integer('employee'));

        abort_unless($viewer->canViewDtrOf($employee), Response::HTTP_FORBIDDEN);

        $from = $this->parseDate($request->query('from')) ?? now()->startOfMonth();
        $until = $this->parseDate($request->query('until')) ?? now()->endOfMonth();

        $data = $dtrService->build($employee, $from, $until);

        $filename = 'dtr-'.str($employee->name)->slug().'-'.$from->toDateString().'_'.$until->toDateString().'.pdf';

        return Pdf::view('pdf.dtr', [
            'employee' => $employee,
            'rows' => $data['rows'],
            'totals' => $data['totals'],
            'periodLabel' => $from->toFormattedDateString().' — '.$until->toFormattedDateString(),
        ])
            ->format('a4')
            ->name($filename)
            ->download();
    }

    protected function parseDate(?string $value): ?Carbon
    {
        if (blank($value)) {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
