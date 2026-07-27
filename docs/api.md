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
