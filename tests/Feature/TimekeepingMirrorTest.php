<?php

use App\Jobs\MirrorPunchToTimekeeping;
use App\Models\ZktecoAttendance;
use App\Services\ZktecoTimekeepingService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

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
 * Build a minimal Active Employees .xlsx and return its raw bytes. The header
 * deliberately uses the misspelled "Biometic ID" the real workbook ships with.
 *
 * @param  list<array{0: string, 1: int}>  $employees  [email, biometricId]
 */
function activeEmployeesXlsx(array $employees): string
{
    $spreadsheet = new Spreadsheet;
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->fromArray(['Email', 'Biometic ID'], null, 'A1');
    $sheet->fromArray($employees, null, 'A2');

    $path = tempnam(sys_get_temp_dir(), 'ae').'.xlsx';
    (new Xlsx($spreadsheet))->save($path);
    $bytes = (string) file_get_contents($path);
    @unlink($path);

    return $bytes;
}

/**
 * Fake every Graph call, serving the given workbook for the file download.
 *
 * @param  list<array{0: string, 1: int}>  $employees
 */
function fakeGraphWithWorkbook(array $employees): void
{
    Http::fake([
        'login.microsoftonline.com/*' => Http::response(['access_token' => 'tok', 'expires_in' => 3600]),
        'graph.microsoft.com/*/drive/root/search*' => Http::response([
            'value' => [['id' => 'file-1', 'name' => 'Active Employees.xlsx', 'parentReference' => ['driveId' => 'drive-1']]],
        ]),
        'graph.microsoft.com/v1.0/drives/*' => Http::response(activeEmployeesXlsx($employees)),
        'graph.microsoft.com/*/lists?*' => Http::response(['value' => [['id' => 'list-1', 'displayName' => 'Timekeeping']]]),
        'graph.microsoft.com/*/lists/*/items' => Http::response(['id' => '1'], 201),
    ]);
    Http::preventStrayRequests();
}

/**
 * Seed the cached employee map directly, bypassing the workbook download.
 *
 * @param  array<int, string>  $map  biometricId => email
 */
function seedEmployeeMap(array $map): void
{
    $cache = [];
    foreach ($map as $bioId => $email) {
        $cache[(string) $bioId] = ['email' => $email, 'name' => 'Test'];
    }
    Cache::put('zkteco_active_employees_map', $cache, now()->addHour());
}

it('builds the map from the workbook and posts the listed employee to Timekeeping', function () {
    fakeGraphWithWorkbook([['romeo@example.com', 104]]);

    $scan = ZktecoAttendance::create([
        'sn' => 'OFFICIAL', 'bio_metric_id' => 104, 'scanned_at' => now(), 'status1' => 0,
    ]);

    (new MirrorPunchToTimekeeping($scan))->handle(app(ZktecoTimekeepingService::class));

    Http::assertSent(fn ($request) => $request->method() === 'POST'
        && str_ends_with($request->url(), '/items')
        && $request['fields']['Title'] === 'TIME-IN'
        && $request['fields']['Class'] === 'romeo@example.com');
});

it('skips biometric ids not present in the Active Employees workbook', function () {
    // Map has someone else; 104 is not legit. No HTTP should be attempted.
    seedEmployeeMap([999 => 'someone@example.com']);
    Http::preventStrayRequests();

    $scan = ZktecoAttendance::create([
        'sn' => 'OFFICIAL', 'bio_metric_id' => 104, 'scanned_at' => now(), 'status1' => 0,
    ]);

    (new MirrorPunchToTimekeeping($scan))->handle(app(ZktecoTimekeepingService::class));

    expect(true)->toBeTrue(); // preventStrayRequests() throws if any call were made
});

it('uses the email from the workbook, not from the local user record', function () {
    seedEmployeeMap([104 => 'workbook-email@example.com']);
    Http::fake([
        'login.microsoftonline.com/*' => Http::response(['access_token' => 'tok', 'expires_in' => 3600]),
        'graph.microsoft.com/*/lists?*' => Http::response(['value' => [['id' => 'list-1', 'displayName' => 'Timekeeping']]]),
        'graph.microsoft.com/*/lists/*/items' => Http::response(['id' => '1'], 201),
    ]);
    Http::preventStrayRequests();

    $scan = ZktecoAttendance::create([
        'sn' => 'OFFICIAL', 'bio_metric_id' => 104, 'scanned_at' => now(), 'status1' => 1,
    ]);

    (new MirrorPunchToTimekeeping($scan))->handle(app(ZktecoTimekeepingService::class));

    Http::assertSent(fn ($request) => $request->method() === 'POST'
        && str_ends_with($request->url(), '/items')
        && $request['fields']['Title'] === 'TIME-OUT'
        && $request['fields']['Class'] === 'workbook-email@example.com');
});

it('does nothing when the mirror is disabled', function () {
    config(['services.sharepoint.timekeeping_enabled' => false]);
    Http::preventStrayRequests();

    $scan = ZktecoAttendance::create([
        'sn' => 'OFFICIAL', 'bio_metric_id' => 104, 'scanned_at' => now(), 'status1' => 0,
    ]);

    (new MirrorPunchToTimekeeping($scan))->handle(app(ZktecoTimekeepingService::class));

    expect(true)->toBeTrue();
});

it('skips the mirror for scans not from the official scanner', function () {
    config(['zkteco.scanner_sn' => 'OFFICIAL']);
    Http::preventStrayRequests();

    $scan = ZktecoAttendance::create([
        'sn' => 'ROGUE', 'bio_metric_id' => 104, 'scanned_at' => now(), 'status1' => 0,
    ]);

    (new MirrorPunchToTimekeeping($scan))->handle(app(ZktecoTimekeepingService::class));

    expect(true)->toBeTrue();
});

it('refreshes the Active Employees map via the console command', function () {
    fakeGraphWithWorkbook([['romeo@example.com', 104], ['jane@example.com', 105]]);

    $this->artisan('zkteco:refresh-employees')
        ->expectsOutputToContain('2 employee(s)')
        ->assertSuccessful();

    expect(app(ZktecoTimekeepingService::class)->findEmployeeByBiometricId(104))
        ->toMatchArray(['email' => 'romeo@example.com']);
});
