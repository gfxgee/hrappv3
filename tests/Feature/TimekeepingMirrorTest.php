<?php

use App\Jobs\MirrorPunchToTimekeeping;
use App\Models\User;
use App\Models\ZktecoAttendance;
use App\Services\ZktecoTimekeepingService;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'services.sharepoint.timekeeping_enabled' => true,
        'services.sharepoint.tenant_id' => 'tenant-id',
        'services.sharepoint.client_id' => 'client-id',
        'services.sharepoint.client_secret' => 'secret',
        'services.sharepoint.site_id' => 'site-id',
        'services.sharepoint.list_name' => 'Timekeeping',
        'services.sharepoint.email_field' => 'Class',
        'zkteco.scanner_sn' => null,
    ]);
});

/**
 * Fake the three Graph calls: token, list discovery, item creation.
 */
function fakeGraph(): void
{
    Http::fake([
        'login.microsoftonline.com/*' => Http::response(['access_token' => 'tok', 'expires_in' => 3600]),
        'graph.microsoft.com/*/lists?*' => Http::response(['value' => [['id' => 'list-1', 'displayName' => 'Timekeeping']]]),
        'graph.microsoft.com/*/lists/*/items' => Http::response(['id' => '1'], 201),
    ]);
    Http::preventStrayRequests();
}

it('posts a punch to the Timekeeping list with the employee email and label', function () {
    fakeGraph();
    User::factory()->create(['bio_metric_id' => 104, 'email' => 'romeo@example.com']);

    $scan = ZktecoAttendance::create([
        'sn' => 'OFFICIAL', 'bio_metric_id' => 104, 'scanned_at' => now(), 'status1' => 0,
    ]);

    (new MirrorPunchToTimekeeping($scan))->handle(app(ZktecoTimekeepingService::class));

    Http::assertSent(fn ($request) => str_contains($request->url(), '/items')
        && $request['fields']['Title'] === 'TIME-IN'
        && $request['fields']['Class'] === 'romeo@example.com');
});

it('labels the punch from the device status byte', function () {
    fakeGraph();
    User::factory()->create(['bio_metric_id' => 104, 'email' => 'romeo@example.com']);

    $scan = ZktecoAttendance::create([
        'sn' => 'OFFICIAL', 'bio_metric_id' => 104, 'scanned_at' => now(), 'status1' => 1,
    ]);

    (new MirrorPunchToTimekeeping($scan))->handle(app(ZktecoTimekeepingService::class));

    Http::assertSent(fn ($request) => str_contains($request->url(), '/items')
        && $request['fields']['Title'] === 'TIME-OUT');
});

it('does nothing when the mirror is disabled', function () {
    config(['services.sharepoint.timekeeping_enabled' => false]);
    Http::preventStrayRequests();

    User::factory()->create(['bio_metric_id' => 104, 'email' => 'romeo@example.com']);
    $scan = ZktecoAttendance::create([
        'sn' => 'OFFICIAL', 'bio_metric_id' => 104, 'scanned_at' => now(), 'status1' => 0,
    ]);

    (new MirrorPunchToTimekeeping($scan))->handle(app(ZktecoTimekeepingService::class));

    // preventStrayRequests() would throw if any HTTP call were attempted.
    expect(true)->toBeTrue();
});

it('skips the mirror for scans not from the official scanner', function () {
    config(['zkteco.scanner_sn' => 'OFFICIAL']);
    Http::preventStrayRequests();

    User::factory()->create(['bio_metric_id' => 104, 'email' => 'romeo@example.com']);
    $scan = ZktecoAttendance::create([
        'sn' => 'ROGUE', 'bio_metric_id' => 104, 'scanned_at' => now(), 'status1' => 0,
    ]);

    (new MirrorPunchToTimekeeping($scan))->handle(app(ZktecoTimekeepingService::class));

    expect(true)->toBeTrue();
});

it('skips the mirror when no employee matches the biometric id', function () {
    Http::preventStrayRequests();

    $scan = ZktecoAttendance::create([
        'sn' => 'OFFICIAL', 'bio_metric_id' => 999, 'scanned_at' => now(), 'status1' => 0,
    ]);

    (new MirrorPunchToTimekeeping($scan))->handle(app(ZktecoTimekeepingService::class));

    expect(true)->toBeTrue();
});
