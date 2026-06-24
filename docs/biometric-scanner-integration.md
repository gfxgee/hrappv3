# Biometric Scanner Integration (ZKTeco)

> Reference note for the owner and future developers. Describes the live
> fingerprint/face scanner integration added to hrappv3 and how to operate,
> configure, and extend it.

## 1. What this does

A ZKTeco biometric scanner on the office network pushes every fingerprint/face
scan to hrappv3 in real time. Each scan is:

1. **Stored raw** for auditing (`zkteco_attendances`), and
2. **Translated into the app's own attendance** (`attendance_logs`) — the same
   table that powers the Daily Time Record (DTR), the clock-in/out widget, the
   DTR PDF, and attendance corrections.
3. *(Optional)* **Mirrored to a SharePoint "Timekeeping" list** via Microsoft
   Graph, for parity with the legacy DF Portal. **Off by default.**

The local `attendance_logs` table is the **single source of truth**. The
SharePoint mirror is an additive, optional side-channel.

### Background: why this differs from DF Portal

The DF Portal has the same scanner endpoints, but because it has *no HR
database* it pushes every punch into a SharePoint "Timekeeping" list and maps
employees by reading an "Active Employees" Excel file from SharePoint via Graph.

hrappv3 **is** the HR database. It already has `users.bio_metric_id`,
`attendance_logs`, a manual `BiometricImportService`, and a live clock-in/out
widget. So the scanner here feeds hrappv3's **native** attendance pipeline
directly — no SharePoint round-trip and no Excel parsing required. The optional
mirror exists only so the SharePoint list can stay populated during the
transition.

## 2. Data flow

```
   ZKTeco device (static IP, plain HTTP)
            │  POST /iclock/cdata?table=ATTLOG
            ▼
   IclockController@receiveRecords
            │  stores raw row → zkteco_attendances
            ├──────────────► SyncAttendanceLogFromScan (queued)
            │                   → maps bio_metric_id → users.id
            │                   → dedupes double-punches
            │                   → toggles clockin / clockout
            │                   → writes attendance_logs   ◀── source of truth
            │
            └──────────────► MirrorPunchToTimekeeping (queued, only if enabled)
                                → maps bio_metric_id → users.email
                                → POSTs item to SharePoint "Timekeeping" via Graph
```

Both jobs are dispatched per scan and run **independently** on the queue, so a
SharePoint/Graph outage never affects the local attendance write (and vice
versa).

## 3. Endpoints (ZKTeco "PUSH SDK" / iclock protocol)

Registered in [`routes/web.php`](../routes/web.php). The three device endpoints
are **unauthenticated** — the scanner speaks plain HTTP over a static IP and
cannot log in.

| Method & path             | Handler          | Purpose                                                        |
| ------------------------- | ---------------- | -------------------------------------------------------------- |
| `GET  /iclock/cdata`      | `handshake`      | Device polls for its options + the latest data timestamp.      |
| `POST /iclock/cdata`      | `receiveRecords` | Receives pushed `ATTLOG` scans; stores + queues them.          |
| `GET  /iclock/getrequest` | `getrequest`     | Command channel; we have no commands, so it just returns `OK`. |
| `GET  /iclock/status`     | `status`         | **Public** heartbeat page (no employee names).                 |
| `GET  /iclock/status/detail` | `statusDetail`| **Private** (auth + HR/admin) detail page.                     |

> **CSRF:** `iclock/*` is exempt from CSRF in
> [`bootstrap/app.php`](../bootstrap/app.php) because the device sends no token.

### Status pages

- **Public** (`/iclock/status`): device online state + scan counts. Shows only
  biometric ID numbers, never employee names — safe to leave open for on-site
  technicians to confirm the scanner is reaching the server.
- **Private** (`/iclock/status/detail`): gated to HR/admin roles
  (`User::isManager()`). Shows recent scans resolved to employee names and the
  resulting attendance logs.

## 4. How a scan becomes an attendance log

Handled by
[`SyncAttendanceLogFromScan`](../app/Jobs/SyncAttendanceLogFromScan.php):

- **Employee match:** `zkteco_attendances.bio_metric_id` → `users.bio_metric_id`.
  No match → logged and skipped (set the employee's Bio ID in the user record).
- **Double-punch dedupe:** a scan within the configured window (default 3 min,
  from `GeneralSettings::biometricDedupeMinutes`, falling back to
  `config('zkteco.dedupe_minutes')`) of an existing log for that user is dropped.
- **Type (clock-in vs clock-out):** inferred by **open-shift toggle**, matching
  the on-screen clock-in/out widget. If the employee has an open clock-in (no
  clock-out within the last 24 h) the scan becomes a **clock-out**; otherwise it
  opens a new shift as a **clock-in**. This is robust even when operators do not
  press the device's IN/OUT function keys.
- Written with `device = 'biometric'` and `remarks = 'Recorded from biometric scanner'`.

## 5. The optional SharePoint mirror

Handled by
[`MirrorPunchToTimekeeping`](../app/Jobs/MirrorPunchToTimekeeping.php) +
[`ZktecoTimekeepingService`](../app/Services/ZktecoTimekeepingService.php).

- **Disabled by default.** Nothing calls Graph unless
  `SHAREPOINT_TIMEKEEPING_ENABLED=true`.
- Replicates DF Portal behaviour: **every** scan from the official scanner is
  recorded (no dedupe, no toggle), labelled from the device status byte:
  `0 → TIME-IN`, `1 → TIME-OUT`, `4 → OT-IN`, `5 → OT-OUT`, else `SCAN`.
- The employee email is resolved **locally** (`users.bio_metric_id → email`), so
  unlike DF Portal this integration **never reads** SharePoint — it only writes
  one list item per punch. That is the tightest the code path can be.

### Important: the "limit to mirror punching" is enforced in code, not by the key

Microsoft Graph **application permissions belong to the app registration**, not
to whichever app holds the secret. If you reuse DF Portal's credentials, the
secret is fully powerful — Azure cannot scope it to "only POST to the
Timekeeping list." The limit here is enforced by three code-side guards:

1. `SHAREPOINT_TIMEKEEPING_ENABLED` defaults to `false`.
2. The service exposes exactly one operation (`recordPunch()`); the
   Excel-reading code from DF Portal was deliberately omitted.
3. The scanner-SN gate (`ZKTECO_SCANNER_SN`) applies to the mirror too.

**Shared-key trade-offs to keep in mind:** with both apps using the same Graph
identity, SharePoint's audit log cannot tell DF Portal's writes from hrappv3's,
and rotating the secret requires updating both apps together. Moving hrappv3 to
its own app registration later is a **config-only** change — no code edits.

## 6. Configuration

### Database tables

`zkteco_devices` (one row per physical scanner) and `zkteco_attendances` (one
row per raw scan) — see the migration
[`..._create_zkteco_tables.php`](../database/migrations/2026_06_23_000001_create_zkteco_tables.php).
The local attendance records live in the existing `attendance_logs` table.

### Config files

- [`config/zkteco.php`](../config/zkteco.php) — official scanner SN, dedupe
  window, and the device handshake option string.
- [`config/services.php`](../config/services.php) — `sharepoint` block for the
  optional Graph mirror.

### Environment variables

| Variable                            | Default        | Purpose                                                  |
| ----------------------------------- | -------------- | -------------------------------------------------------- |
| `ZKTECO_SCANNER_SN`                 | *(empty)*      | If set, only this device's scans sync. Empty = all sync. |
| `ZKTECO_DEDUPE_MINUTES`             | `3`            | Fallback double-punch window.                            |
| `SHAREPOINT_TIMEKEEPING_ENABLED`    | `false`        | Master switch for the SharePoint mirror.                 |
| `SHAREPOINT_CLIENT_ID`              | *(empty)*      | App-only Graph registration (may reuse DF Portal's).     |
| `SHAREPOINT_CLIENT_SECRET`          | *(empty)*      | "                                                        |
| `SHAREPOINT_TENANT_ID`              | *(empty)*      | "                                                        |
| `SHAREPOINT_SITE_ID`                | *(empty)*      | Target SharePoint site.                                  |
| `SHAREPOINT_TIMEKEEPING_LIST`       | `Timekeeping`  | List display name to write to.                           |
| `SHAREPOINT_TIMEKEEPING_EMAIL_FIELD`| `Class`        | Internal column name of the list's email field.          |

## 7. Operational notes

- **Queue worker must be running.** Both the local sync and the mirror are
  queued (`QUEUE_CONNECTION=database`). Without a worker, scans are stored raw
  but never become attendance logs. Run `php artisan queue:work`.
- **Employee setup:** an employee only syncs once their `bio_metric_id` is set
  (Users resource in the admin panel) to match their enrolled ID on the device.
- **Pointing the device:** configure the scanner's server/cloud (ADMS) address
  to this app's host. It will hit `/iclock/cdata` automatically.
- **Verifying live:** open `/iclock/status` to confirm the device is checking in
  and scans are arriving; `/iclock/status/detail` (as HR/admin) to see how scans
  resolved to employees and logs.

## 8. Files added / changed

**Added**

- `app/Http/Controllers/IclockController.php`
- `app/Jobs/SyncAttendanceLogFromScan.php`
- `app/Jobs/MirrorPunchToTimekeeping.php`
- `app/Services/ZktecoTimekeepingService.php`
- `app/Models/ZktecoDevice.php`, `app/Models/ZktecoAttendance.php`
- `config/zkteco.php`
- `database/migrations/2026_06_23_000001_create_zkteco_tables.php`
- `resources/views/iclock/status.blade.php`
- `resources/views/iclock/status-detail.blade.php`
- `tests/Feature/IclockEndpointTest.php`
- `tests/Feature/TimekeepingMirrorTest.php`

**Changed**

- `routes/web.php` — registered the five `iclock` routes.
- `bootstrap/app.php` — CSRF exemption for `iclock/*`.
- `config/services.php` — added the `sharepoint` block.
- `.env.example` — added `ZKTECO_*` and `SHAREPOINT_*` keys.

## 9. Tests

- [`tests/Feature/IclockEndpointTest.php`](../tests/Feature/IclockEndpointTest.php)
  — handshake, record ingestion, the toggle/dedupe/scanner-SN/unmatched rules,
  and the public vs. private status pages.
- [`tests/Feature/TimekeepingMirrorTest.php`](../tests/Feature/TimekeepingMirrorTest.php)
  — the Graph mirror with `Http::fake()` + `preventStrayRequests()`, covering
  the label mapping, the disabled switch, the scanner-SN gate, and unmatched IDs.

Run them:

```bash
php artisan test --compact --filter='IclockEndpoint|TimekeepingMirror'
```

## 10. Extending

- **Per-app Graph identity:** give hrappv3 its own Azure app registration (ideally
  `Sites.Selected` scoped to just the Timekeeping site) and swap the
  `SHAREPOINT_*` values — no code changes needed.
- **Admin UI for devices:** `zkteco_devices` could back a small Filament page if
  managing multiple scanners becomes useful.
- **Explicit IN/OUT:** if the device reliably sends status bytes, the local
  toggle in `SyncAttendanceLogFromScan::resolveType()` could honor `status1`
  instead of inferring — the raw bytes are already stored.
