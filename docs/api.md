# HR App — Read-only JSON API

Small JSON endpoints used by the SharePoint / Power Automate → Teams
automation. All are **GET**, return **JSON**, and are **read-only**.

- **Base URL:** your app origin + `/api` (e.g. `https://hrapp.example.com/api`).
- **Auth:** none — these are unauthenticated feeds intended for the internal
  automation flow. Don't expose sensitive data here beyond what's documented.
- **Dates:** every `?date=` accepts `YYYY-MM-DD`. If omitted or unparseable, it
  falls back to **today**. Useful for testing the payload on a weekday when the
  current day (e.g. a weekend) has no data.

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
    "reason": "Family trip",
    "start_time": "09:00",
    "end_time": "13:00",
    "duration_hours": 4
  }
]
```
`start_time` / `end_time` / `duration_hours` are `null` when the leave has no
times. Cancelled and rejected leaves are excluded. Empty array when nobody is on
leave.

### `GET /api/leaves/wfh`
Everyone **working from home** on the date. Same query param and response shape
as `/leaves/today`.

---

## On-call ("late dev")

A weekly rotation. Each week has an **owner**; on any day the owner is on leave,
the next available developer **stands in** for that day.

### `GET /api/on-call/current`
The **week's owner** for the week containing the date.

Query: `date` (optional).

Response:
```json
{
  "name": "Dev A",
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
  "is_substitute": true,
  "covering_for": "Dev A",
  "date": "2026-07-29"
}
```
- `is_substitute` — `true` when a stand-in is covering for the owner.
- `covering_for` — the owner's name when `is_substitute` is `true`, else `null`.
- `name` is `null` when nobody is available.

---

## Related feeds

- `GET /api/upcoming/leaves` — upcoming leaves within a window.
- `GET /api/upcoming/birthdays` — upcoming birthdays.
- `GET /api/upcoming/holidays` — upcoming holidays.

Each accepts `?days=1..365` to override the default look-ahead window.

## Outbound Teams events

The app also **pushes** to the Power Automate flow (`services.teams.flow_url`)
for on-call, keyed by an `event` field:

- `on_call.assigned` — the week's owner, sent Monday.
- `on_call.standin` — a stand-in covering today (sent the morning the owner is out).
