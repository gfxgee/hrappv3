<?php

use App\Jobs\SyncAttendanceLogFromScan;
use App\Models\AttendanceLog;
use App\Models\User;
use App\Models\ZktecoAttendance;
use App\Models\ZktecoDevice;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Role;

/**
 * Build a raw ATTLOG push body line: USER_ID  YYYY-MM-DD HH:mm:ss  STATUS1.
 */
function attlogLine(int $bioId, string $dateTime, int $status1 = 0): string
{
    return "{$bioId}\t{$dateTime}\t{$status1}\t0\t0";
}

it('registers the device and returns options on handshake', function () {
    $response = $this->get('/iclock/cdata?SN=TESTSN123');

    $response->assertOk();
    $response->assertSee('GET OPTION FROM:TESTSN123');
    $response->assertSee('Realtime=1');

    expect(ZktecoDevice::where('sn', 'TESTSN123')->exists())->toBeTrue();
});

it('stores pushed ATTLOG records and queues a sync job for each', function () {
    Queue::fake();

    $body = attlogLine(104, '2026-06-23 09:00:00', 0)."\r\n".attlogLine(105, '2026-06-23 09:01:00', 0);

    $response = $this->call('POST', '/iclock/cdata?SN=TESTSN123&table=ATTLOG', [], [], [], [], $body);

    $response->assertOk();
    $response->assertSee('OK: 2');

    expect(ZktecoAttendance::count())->toBe(2)
        ->and(ZktecoAttendance::first()->bio_metric_id)->toBe(104);

    Queue::assertPushed(SyncAttendanceLogFromScan::class, 2);
});

it('ignores non-ATTLOG tables but still acknowledges', function () {
    $response = $this->call('POST', '/iclock/cdata?SN=TESTSN123&table=OPERLOG', [], [], [], [], 'whatever');

    $response->assertOk();
    expect(ZktecoAttendance::count())->toBe(0);
});

it('syncs a scan into an attendance log as a clock-in then a clock-out', function () {
    config(['zkteco.scanner_sn' => null]);

    $user = User::factory()->create(['bio_metric_id' => 104]);

    $morning = ZktecoAttendance::create([
        'sn' => 'TESTSN123', 'bio_metric_id' => 104, 'scanned_at' => '2026-06-23 09:00:00', 'status1' => 0,
    ]);
    (new SyncAttendanceLogFromScan($morning))->handle();

    $evening = ZktecoAttendance::create([
        'sn' => 'TESTSN123', 'bio_metric_id' => 104, 'scanned_at' => '2026-06-23 18:00:00', 'status1' => 1,
    ]);
    (new SyncAttendanceLogFromScan($evening))->handle();

    $logs = AttendanceLog::where('user_id', $user->id)->orderBy('id')->get();

    expect($logs)->toHaveCount(2)
        ->and($logs[0]->type)->toBe('clockin')
        ->and($logs[0]->device)->toBe('biometric')
        ->and($logs[1]->type)->toBe('clockout');
});

it('drops an accidental double-punch within the dedupe window', function () {
    config(['zkteco.scanner_sn' => null, 'zkteco.dedupe_minutes' => 3]);

    $user = User::factory()->create(['bio_metric_id' => 104]);

    $first = ZktecoAttendance::create([
        'sn' => 'TESTSN123', 'bio_metric_id' => 104, 'scanned_at' => '2026-06-23 09:00:00', 'status1' => 0,
    ]);
    (new SyncAttendanceLogFromScan($first))->handle();

    $second = ZktecoAttendance::create([
        'sn' => 'TESTSN123', 'bio_metric_id' => 104, 'scanned_at' => '2026-06-23 09:01:00', 'status1' => 0,
    ]);
    (new SyncAttendanceLogFromScan($second))->handle();

    expect(AttendanceLog::where('user_id', $user->id)->count())->toBe(1);
});

it('skips syncing when the scan is not from the configured official scanner', function () {
    config(['zkteco.scanner_sn' => 'OFFICIAL-SN']);

    $user = User::factory()->create(['bio_metric_id' => 104]);

    $scan = ZktecoAttendance::create([
        'sn' => 'ROGUE-SN', 'bio_metric_id' => 104, 'scanned_at' => '2026-06-23 09:00:00', 'status1' => 0,
    ]);
    (new SyncAttendanceLogFromScan($scan))->handle();

    expect(AttendanceLog::where('user_id', $user->id)->count())->toBe(0);
});

it('skips syncing when no employee matches the biometric id', function () {
    config(['zkteco.scanner_sn' => null]);

    $scan = ZktecoAttendance::create([
        'sn' => 'TESTSN123', 'bio_metric_id' => 999, 'scanned_at' => '2026-06-23 09:00:00', 'status1' => 0,
    ]);
    (new SyncAttendanceLogFromScan($scan))->handle();

    expect(AttendanceLog::count())->toBe(0);
});

it('exposes a public status page without sensitive employee data', function () {
    User::factory()->create(['name' => 'Jane Secret', 'bio_metric_id' => 104]);
    ZktecoDevice::create(['sn' => 'TESTSN123', 'last_seen' => now()]);
    ZktecoAttendance::create([
        'sn' => 'TESTSN123', 'bio_metric_id' => 104, 'scanned_at' => now(), 'status1' => 0,
    ]);

    $response = $this->get('/iclock/status');

    $response->assertOk();
    $response->assertSee('Biometric Scanner Status');
    $response->assertDontSee('Jane Secret');
});

it('gates the detail status page to managers', function () {
    Role::findOrCreate('hr');

    $employee = User::factory()->create(['bio_metric_id' => 104]);
    $manager = User::factory()->create();
    $manager->assignRole('hr');

    $this->actingAs($employee)->get('/iclock/status/detail')->assertForbidden();
    $this->actingAs($manager)->get('/iclock/status/detail')->assertOk();
});
