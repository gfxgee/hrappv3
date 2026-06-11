@php
    /**
     * @var \App\Models\User $employee
     * @var array $rows
     * @var array $totals
     * @var string $periodLabel
     */
    $humanMinutes = function (int $minutes): string {
        $minutes = abs($minutes);
        $h = intdiv($minutes, 60);
        $m = $minutes % 60;

        return match (true) {
            $h === 0 => "{$m}m",
            $m === 0 => "{$h}h",
            default => "{$h}h {$m}m",
        };
    };

    // Filament colour name → [text, background] hex for the PDF badges.
    $palette = [
        'success' => ['#15803d', '#dcfce7'],
        'danger' => ['#b91c1c', '#fee2e2'],
        'warning' => ['#b45309', '#fef3c7'],
        'info' => ['#1d4ed8', '#dbeafe'],
        'verified' => ['#ffffff', '#1e1242'],
        'gray' => ['#374151', '#f3f4f6'],
    ];

    $statusColors = [
        'Present' => 'success',
        'Absent' => 'danger',
        'Holiday' => 'info',
        'Leave' => 'info',
        'Rest day' => 'gray',
    ];
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Daily Time Record — {{ $employee->name }}</title>
    <style>
        * { box-sizing: border-box; }
        @page { margin: 16mm 14mm; }
        body {
            font-family: 'DejaVu Sans', 'Segoe UI', Arial, sans-serif;
            color: #1f2937;
            font-size: 11px;
            margin: 0;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            border-bottom: 2px solid #271A3D;
            padding-bottom: 10px;
            margin-bottom: 16px;
        }
        .brand { font-size: 18px; font-weight: 700; color: #271A3D; }
        .brand span { color: #F99F29; }
        .title { font-size: 15px; font-weight: 700; margin: 6px 0 2px; }
        .muted { color: #6b7280; }
        .period { text-align: right; font-weight: 600; color: #374151; }
        table { width: 100%; border-collapse: collapse; }
        thead th {
            text-align: left;
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: .04em;
            color: #6b7280;
            border-bottom: 1px solid #d1d5db;
            padding: 6px 8px;
        }
        tbody td { padding: 5px 8px; border-bottom: 1px solid #f3f4f6; }
        .num { text-align: right; font-variant-numeric: tabular-nums; }
        tbody tr.rest { background: #f9fafb; }
        tfoot td {
            padding: 8px;
            border-top: 2px solid #9ca3af;
            font-weight: 700;
            font-variant-numeric: tabular-nums;
        }
        .badge {
            display: inline-block;
            border-radius: 6px;
            padding: 1px 6px;
            font-size: 9px;
            font-weight: 600;
            white-space: nowrap;
        }
        .ot-badge { margin-left: 3px; }
    </style>
</head>
<body>
    <div class="header">
        <div>
            <div class="brand">Digitalfeet<span>.HR</span></div>
            <div class="title">Daily Time Record</div>
            <div class="muted">
                {{ $employee->name }}@if ($employee->department?->name) · {{ $employee->department->name }}@endif
            </div>
            <div class="muted" style="font-size: 10px;">{{ $employee->email }}</div>
        </div>
        <div class="period">{{ $periodLabel }}</div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Day</th>
                <th>Time In</th>
                <th>Time Out</th>
                <th class="num">Hours</th>
                <th class="num">Late</th>
                <th class="num">Undertime</th>
                <th class="num">OT</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $row)
                <tr class="{{ in_array($row['status'], ['Rest day', 'Holiday'], true) ? 'rest' : '' }}">
                    <td>{{ $row['date']->format('M d') }}</td>
                    <td class="muted">{{ $row['day'] }}</td>
                    <td>{{ $row['time_in'] ?? '—' }}</td>
                    <td>{{ $row['time_out'] ?? '—' }}</td>
                    <td class="num">{{ $row['hours'] ?: '—' }}</td>
                    <td class="num">{{ $row['late'] ? $humanMinutes($row['late']) : '—' }}</td>
                    <td class="num">{{ $row['undertime'] ? $humanMinutes($row['undertime']) : '—' }}</td>
                    <td class="num">
                        @forelse ($row['overtime_breakdown'] as $entry)
                            @php([$fg, $bg] = $palette[$entry['status']->color()] ?? $palette['gray'])
                            <span class="badge ot-badge" style="color: {{ $fg }}; background: {{ $bg }};">
                                {{ rtrim(rtrim(number_format($entry['hours'], 2), '0'), '.') }}h
                            </span>
                        @empty
                            —
                        @endforelse
                    </td>
                    <td>
                        @php([$fg, $bg] = $palette[$statusColors[$row['status']] ?? 'gray'])
                        <span class="badge" style="color: {{ $fg }}; background: {{ $bg }};">{{ $row['status'] }}</span>
                    </td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="4">Totals</td>
                <td class="num">{{ $totals['hours'] }}</td>
                <td class="num">{{ $totals['late'] ? $humanMinutes($totals['late']) : '—' }}</td>
                <td class="num">{{ $totals['undertime'] ? $humanMinutes($totals['undertime']) : '—' }}</td>
                <td class="num">
                    {{ $totals['overtime'] }}
                    @if ($totals['overtime_pending'] > 0)
                        <span class="badge ot-badge" style="color: #1d4ed8; background: #dbeafe;">
                            +{{ rtrim(rtrim(number_format($totals['overtime_pending'], 2), '0'), '.') }}h pending
                        </span>
                    @endif
                </td>
                <td class="muted" style="font-weight: 600; font-size: 9px;">
                    {{ $totals['present'] }} present · {{ $totals['absent'] }} absent · {{ $totals['leave'] }} leave
                </td>
            </tr>
        </tfoot>
    </table>
</body>
</html>
