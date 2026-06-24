<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="refresh" content="30">
    <title>Biometric Scanner Status (Detail)</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-950 text-gray-100 min-h-screen font-sans">

    <div class="max-w-6xl mx-auto px-4 py-10">

        {{-- Header --}}
        <div class="mb-8 flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold tracking-tight">Biometric Scanner Status &mdash; Detail</h1>
                <p class="text-gray-400 text-sm mt-1">Auto-refreshes every 30 seconds &mdash; {{ now()->format('d M Y, H:i:s') }}</p>
            </div>
            <span class="text-xs text-gray-500 bg-gray-800 px-3 py-1 rounded-full">/iclock/status/detail</span>
        </div>

        {{-- Devices --}}
        <section class="mb-8">
            <h2 class="text-sm font-semibold uppercase tracking-widest text-gray-400 mb-3">Connected Devices</h2>
            @if ($devices->isEmpty())
                <div class="bg-gray-900 border border-gray-800 rounded-xl p-6 text-center text-gray-500">
                    No devices have checked in yet.
                </div>
            @else
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    @foreach ($devices as $device)
                        @php
                            $minutesAgo = $device->last_seen ? now()->diffInMinutes($device->last_seen) : null;
                            $isOnline   = $minutesAgo !== null && $minutesAgo < 5;
                        @endphp
                        <div class="bg-gray-900 border border-gray-800 rounded-xl p-5 flex items-start gap-4">
                            <span class="mt-1 inline-block w-2.5 h-2.5 rounded-full {{ $isOnline ? 'bg-green-400' : 'bg-gray-600' }}"></span>
                            <div class="space-y-1">
                                <p class="font-mono font-semibold text-white">{{ $device->sn }}</p>
                                <p class="text-sm text-gray-400">
                                    <span class="text-gray-600 text-xs uppercase tracking-wide">Last seen&nbsp;</span>
                                    {{ $device->last_seen ? $device->last_seen->diffForHumans().' — '.$device->last_seen->format('d M Y, H:i:s') : 'Never' }}
                                </p>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </section>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">

            {{-- Raw scans --}}
            <section>
                <h2 class="text-sm font-semibold uppercase tracking-widest text-gray-400 mb-3">
                    Recent Scans <span class="ml-2 text-gray-600 normal-case tracking-normal font-normal">(last 50)</span>
                </h2>
                @if ($records->isEmpty())
                    <div class="bg-gray-900 border border-gray-800 rounded-xl p-6 text-center text-gray-500">No scans yet.</div>
                @else
                    <div class="bg-gray-900 border border-gray-800 rounded-xl overflow-hidden">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-gray-800 text-gray-400 text-xs uppercase tracking-wider">
                                    <th class="px-4 py-3 text-left">Scanner Time</th>
                                    <th class="px-4 py-3 text-left">Employee</th>
                                    <th class="px-4 py-3 text-left">In/Out</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-800">
                                @foreach ($records as $record)
                                    <tr class="hover:bg-gray-800/50 transition-colors">
                                        <td class="px-4 py-3 font-mono text-gray-300 whitespace-nowrap">{{ $record->scanned_at->format('d M, H:i:s') }}</td>
                                        <td class="px-4 py-3">
                                            @if ($record->user)
                                                {{ $record->user->name }}
                                            @else
                                                <span class="text-amber-400">Unmatched</span>
                                            @endif
                                            <span class="text-blue-400 font-mono text-xs">#{{ $record->bio_metric_id }}</span>
                                        </td>
                                        <td class="px-4 py-3">
                                            @switch($record->status1)
                                                @case(0) <span class="text-green-400">In</span> @break
                                                @case(1) <span class="text-red-400">Out</span> @break
                                                @case(4) <span class="text-purple-400">OT In</span> @break
                                                @case(5) <span class="text-purple-400">OT Out</span> @break
                                                @case(null) <span class="text-gray-600">&mdash;</span> @break
                                                @default <span class="text-gray-400">{{ $record->status1 }}</span>
                                            @endswitch
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>

            {{-- Resulting attendance logs --}}
            <section>
                <h2 class="text-sm font-semibold uppercase tracking-widest text-gray-400 mb-3">
                    Attendance Logs Created <span class="ml-2 text-gray-600 normal-case tracking-normal font-normal">(last 50)</span>
                </h2>
                @if ($logs->isEmpty())
                    <div class="bg-gray-900 border border-gray-800 rounded-xl p-6 text-center text-gray-500">No logs created from the scanner yet.</div>
                @else
                    <div class="bg-gray-900 border border-gray-800 rounded-xl overflow-hidden">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-gray-800 text-gray-400 text-xs uppercase tracking-wider">
                                    <th class="px-4 py-3 text-left">Time</th>
                                    <th class="px-4 py-3 text-left">Employee</th>
                                    <th class="px-4 py-3 text-left">Type</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-800">
                                @foreach ($logs as $log)
                                    <tr class="hover:bg-gray-800/50 transition-colors">
                                        <td class="px-4 py-3 font-mono text-gray-300 whitespace-nowrap">{{ $log->created_at->format('d M, H:i:s') }}</td>
                                        <td class="px-4 py-3">{{ $log->user?->name ?? '—' }}</td>
                                        <td class="px-4 py-3">
                                            @if ($log->type === 'clockin')
                                                <span class="text-green-400">Clock in</span>
                                            @else
                                                <span class="text-red-400">Clock out</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>

        </div>

    </div>

</body>
</html>
