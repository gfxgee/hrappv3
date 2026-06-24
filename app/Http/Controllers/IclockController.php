<?php

namespace App\Http\Controllers;

use App\Jobs\MirrorPunchToTimekeeping;
use App\Jobs\SyncAttendanceLogFromScan;
use App\Models\AttendanceLog;
use App\Models\ZktecoAttendance;
use App\Models\ZktecoDevice;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * ZKTeco "PUSH SDK" (iclock) protocol endpoints.
 *
 * Fingerprint/face scanners on the local network speak this plain-text HTTP
 * protocol over a static IP, so the push endpoints are unauthenticated — the
 * device cannot log in. Each scan is stored raw for auditing and queued for
 * translation into the app's attendance logs via {@see SyncAttendanceLogFromScan}.
 */
class IclockController extends Controller
{
    /**
     * Device handshake: the scanner polls this on a timer to fetch its options
     * and learn how far back the server already has data ("Stamp").
     */
    public function handshake(Request $request): Response
    {
        $sn = (string) $request->query('SN', '');

        $this->touchDevice($sn);

        $latest = ZktecoAttendance::query()
            ->where('sn', $sn)
            ->orderByDesc('scanned_at')
            ->first();

        $stamp = $latest?->scanned_at->timestamp ?? now()->timestamp;

        $lines = ['OK', 'GET OPTION FROM:'.$sn, 'Stamp='.$stamp, 'OpStamp='.now()->timestamp];

        foreach (config('zkteco.options', []) as $key => $value) {
            $lines[] = $key.'='.$value;
        }

        return $this->plain(implode("\r\n", $lines));
    }

    /**
     * Receive pushed records. Only ATTLOG (attendance) tables are stored; every
     * parsed line is queued for syncing into attendance_logs.
     */
    public function receiveRecords(Request $request): Response
    {
        $sn = (string) $request->query('SN', '');
        $table = strtoupper((string) $request->query('table', ''));

        $this->touchDevice($sn);

        if ($table !== 'ATTLOG') {
            return $this->plain('OK');
        }

        $lines = preg_split('/[\r\n]+/', trim($request->getContent())) ?: [];
        $count = 0;

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            // ATTLOG format: USER_ID  YYYY-MM-DD HH:mm:ss  STATUS1..STATUS5
            $fields = preg_split('/[\s\t,]+/', $line) ?: [];

            if (count($fields) < 2) {
                continue;
            }

            try {
                $scannedAt = $fields[1].(isset($fields[2]) && str_contains($fields[2], ':') ? ' '.$fields[2] : '');

                $record = ZktecoAttendance::create([
                    'sn' => $sn,
                    'bio_metric_id' => (int) $fields[0],
                    'scanned_at' => $scannedAt,
                    'status1' => $this->nullableInt($fields[3] ?? null),
                    'status2' => $this->nullableInt($fields[4] ?? null),
                    'status3' => $this->nullableInt($fields[5] ?? null),
                    'status4' => $this->nullableInt($fields[6] ?? null),
                    'status5' => $this->nullableInt($fields[7] ?? null),
                    'raw' => $line,
                ]);

                SyncAttendanceLogFromScan::dispatch($record);

                if (config('services.sharepoint.timekeeping_enabled')) {
                    MirrorPunchToTimekeeping::dispatch($record);
                }

                $count++;
            } catch (\Throwable $e) {
                Log::error('ZKTeco: record parse error', ['error' => $e->getMessage(), 'line' => $line, 'sn' => $sn]);
            }
        }

        return $this->plain("OK: {$count}");
    }

    /**
     * Command channel. The device polls this for queued commands; we have none,
     * so we always acknowledge with OK.
     */
    public function getrequest(): Response
    {
        return $this->plain('OK');
    }

    /**
     * Public, non-sensitive heartbeat: which devices are online and the most
     * recent scan activity. Exposes only biometric ids, never employee names.
     */
    public function status(): View
    {
        $devices = ZktecoDevice::query()->orderByDesc('last_seen')->get();

        $lastLogs = ZktecoAttendance::query()
            ->whereIn('sn', $devices->pluck('sn'))
            ->orderByDesc('scanned_at')
            ->get()
            ->groupBy('sn')
            ->map(fn ($rows) => $rows->first());

        $scansToday = ZktecoAttendance::query()
            ->whereDate('scanned_at', now()->toDateString())
            ->count();

        return view('iclock.status', compact('devices', 'lastLogs', 'scansToday'));
    }

    /**
     * Private detail view (auth + HR/admin only): recent raw scans resolved to
     * employees plus the attendance logs they produced.
     */
    public function statusDetail(Request $request): View
    {
        abort_unless($request->user()?->isManager() ?? false, HttpResponse::HTTP_FORBIDDEN);

        $devices = ZktecoDevice::query()->orderByDesc('last_seen')->get();

        $records = ZktecoAttendance::query()
            ->with('user:id,name,bio_metric_id')
            ->orderByDesc('scanned_at')
            ->limit(50)
            ->get();

        $logs = AttendanceLog::query()
            ->with('user:id,name')
            ->where('device', SyncAttendanceLogFromScan::DEVICE)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        return view('iclock.status-detail', compact('devices', 'records', 'logs'));
    }

    /**
     * Upsert the device row and bump its last-seen timestamp.
     */
    protected function touchDevice(string $sn): void
    {
        if ($sn === '') {
            return;
        }

        ZktecoDevice::query()->updateOrCreate(['sn' => $sn], ['last_seen' => now()]);
    }

    protected function nullableInt(mixed $value): ?int
    {
        return ($value !== null && $value !== '') ? (int) $value : null;
    }

    protected function plain(string $body): Response
    {
        return response($body, 200)->header('Content-Type', 'text/plain');
    }
}
