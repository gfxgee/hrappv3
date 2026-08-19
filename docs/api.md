# HR App — Read-only JSON API

Small JSON endpoints used by the SharePoint / Power Automate → Teams
automation. All are **GET**, return **JSON**, and are **read-only**.

- **Base URL:** your app origin + `/api` (e.g. `https://hrapp.example.com/api`).
- **Auth:** none — these are unauthenticated feeds intended for the internal
  automation flow. Don't expose sensitive data here beyond what's documented.
- **Dates:** every `?date=` accepts `YYYY-MM-DD`. If omitted or unparseable, it
  falls back to **today**. Useful for testing the payload on a weekday when the
  current day (e.g. a weekend) has no data.
- **Names:** wherever a person appears, payloads carry both `name` (the full
  legal name, e.g. `Nik Cyrell Z. Yabo`) and `display_name` (the short friendly
  name, e.g. `Nik`). `display_name` falls back to the full name when unset, so
  it is never blank &mdash; prefer it for Teams messages and cards.

---

## Leaves

### `GET /api/leaves/today`
Everyone **on leave** on the date (excludes Work-From-Home).

Query: `date` (optional).

Response — array of:
```json
[
  {
    "name": "Jane Doe",
    "display_name": "Jane",
    "type": "Vacation Leave",
    "type_value": "vacation",
    "reason": "Family trip",
    "start_time": "09:00",
    "end_time": "13:00",
    "duration_hours": 4
  }
]
```
`type` is the human-readable label; `type_value` is the stable enum key —
**branch on `type_value`**, since labels may be reworded.

| `type_value` | `type` |
| --- | --- |
| `wfh` | Work from Home |
| `vacation` | Vacation Leave |
| `sick` | Sick Leave |
| `emergency` | Emergency Leave |
| `bereavement` | Bereavement Leave |
| `maternity` | Maternity Leave |
| `paternity` | Paternity Leave |
| `lwop` | Leave Without Pay |

`start_time` / `end_time` / `duration_hours` are `null` when the leave has no
times. Cancelled and rejected leaves are excluded. Empty array when nobody is on
leave.

### `GET /api/leaves/wfh`
Everyone **working from home** on the date. Same query param and response shape
as `/leaves/today`, minus `type` / `type_value` — every entry is Work from Home.

---

## On-call ("late dev")

A weekly rotation over an ordered roster of developers (managed in the admin
under **HR Management → On-Call Rotation**).

- Each week has an **owner** — the available roster member who was on-call least
  recently, tie-broken by roster order. This gives a plain `1-2-3-4` rotation.
- If the owner would be out the **whole** week, they're skipped and take the
  **next** week instead (so `1-2-3-4` becomes `1-3-2-4`).
- On a day the owner is out for a **full day**, the next available developer
  **stands in** for that day only. Standing in doesn't consume their own turn.

**What counts as unavailable:** only a full-day absence — a multi-day leave, a
leave with no times, or a timed leave covering a whole working day. A
**partial-day** leave (e.g. 10:00–13:00) keeps the person on-call, and **WFH
never** makes someone unavailable.

### `GET /api/on-call/current`
The **week's owner** for the week containing the date.

Query: `date` (optional).

Response:
```json
{
  "name": "Dev A",
  "display_name": "Dev A",
  "week_start": "2026-07-27",
  "week_end": "2026-08-02"
}
```
`name` is `null` when the roster is empty or nobody can be assigned.

### `GET /api/on-call/today`
Who is **effectively on-call** on the date — the owner when they're in,
otherwise the stand-in covering that day. Use this for "who do I contact now".

Query: `date` (optional).

Response:
```json
{
  "name": "Dev B",
  "display_name": "Dev B",
  "is_substitute": true,
  "covering_for": "Dev A",
  "covering_for_display_name": "Dev A",
  "date": "2026-07-29"
}
```
- `is_substitute` — `true` when a stand-in is covering for the owner.
- `covering_for` / `covering_for_display_name` — the owner's names when
  `is_substitute` is `true`, else `null`.
- `name` / `display_name` are `null` when nobody is available.

---

## Related feeds

Each accepts `?days=1..365` to override the default look-ahead window (the
app's "Coming up" setting).

### `GET /api/upcoming/leaves`
Leaves **starting after today**, within the window, soonest first. Excludes WFH,
cancelled, and rejected.

```json
[
  {
    "name": "Jane Doe",
    "display_name": "Jane",
    "type": "Sick Leave",
    "type_value": "sick",
    "reason": "Checkup",
    "start_date": "2026-07-30",
    "end_date": "2026-07-31",
    "days_until": 3
  }
]
```
`type` / `type_value` use the same mapping as `/leaves/today` above.

### `GET /api/upcoming/birthdays`
Active employees whose birthday falls within the window, soonest first.

```json
[
  { "name": "Jane Doe", "display_name": "Jane", "date": "2026-08-01", "days_until": 5 }
]
```

### `GET /api/upcoming/anniversaries`
Active employees whose **work anniversary** falls within the window, soonest
first. Employees hired this year are omitted (no anniversary yet), as are those
with no hire date.

```json
[
  { "name": "Jane Doe", "display_name": "Jane", "years": 6, "date": "2026-08-01", "days_until": 5 }
]
```
`years` is the anniversary being reached (e.g. `6` = their 6th year).

### `GET /api/upcoming/holidays`
Active holidays within the window, soonest first.

```json
[
  {
    "name": "Independence Day",
    "emoji": "🎉",
    "date": "2026-08-01",
    "duration": "Full day",
    "days_until": 5
  }
]
```

---

## Payroll summary

### `GET /api/reports/leave-summary`
Per-employee leave and overtime totals for a date range — **one request, one
payload**, built for auto-filling payslips.

Query: `start`, `end` (both `YYYY-MM-DD`, optional). Omitted values default to
the **current calendar month**; a reversed range is swapped rather than
returning nothing.

```json
{
  "start_date": "2026-08-01",
  "end_date": "2026-08-31",
  "employee_count": 13,
  "employees": [
    {
      "name": "Nik Cyrell Z. Yabo",
      "display_name": "Nik",
      "email": "nik@digitalfeet.com",
      "department": "Development",
      "leaves": {
        "wfh":         { "days": 2,    "requests": 2 },
        "vacation":    { "days": 1,    "requests": 1 },
        "sick":        { "days": 0.38, "requests": 1 },
        "emergency":   { "days": 0,    "requests": 0 },
        "bereavement": { "days": 0,    "requests": 0 },
        "maternity":   { "days": 0,    "requests": 0 },
        "paternity":   { "days": 0,    "requests": 0 },
        "lwop":        { "days": 3,    "requests": 1 }
      },
      "total_leave_days": 6.38,
      "overtime_hours": 2.5,
      "overtime_requests": 1
    }
  ]
}
```

**Rules that matter for payroll:**

- **Active employees only** — inactive/left employees are excluded. `employee_count`
  is how many rows are in `employees`.
- **Every active employee is listed**, even with nothing in the range, and **all
  eight leave-type keys are always present** (zeroed). No missing keys, no nulls —
  safe to index directly in a flow.
- **Only approved and HR-verified records count.** Pending, rejected, and
  cancelled leave/overtime are ignored, so nothing awaiting approval affects pay.
- **Days are clipped to the range.** A leave running Jul 31 → Aug 4 contributes
  only its in-range working days to an August report.
- **Days are working days**, so weekends and holidays are free, and a partial-day
  leave costs a fraction (10:00–13:00 of an 8-hour day = `0.38`).
- `days`, `total_leave_days`, and `overtime_hours` can be **fractional** — type
  them as `number` (not `integer`) in a Power Automate Parse JSON schema.
  Whole values serialise without a decimal (`3`, not `3.0`).

## Outbound Teams events

The app also **pushes** to the Power Automate flow (`services.teams.flow_url`).
Every payload carries an `event` key to branch on, plus a ready-made `text`
summary. Null fields are stripped before sending.

| `event` | When | Extra fields |
| --- | --- | --- |
| `on_call.assigned` | Monday 00:05, when the week's owner is set | `start_date`, `end_date` |
| `on_call.standin` | Daily 07:30, only if the owner is out that day | `covering_for`, `request_date` |

Both also include `category` (`On-Call`), `icon`, `employee`, `email`, `photo`,
and `department`. Existing leave/overtime events (`leave.filed`,
`overtime.filed`, …) are unchanged.
