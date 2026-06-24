<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="refresh" content="30">
    <title>Biometric Scanner Status</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-950 text-gray-100 min-h-screen font-sans">

    <div class="max-w-4xl mx-auto px-4 py-10">

        {{-- Header --}}
        <div class="mb-8 flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold tracking-tight">Biometric Scanner Status</h1>
                <p class="text-gray-400 text-sm mt-1">Auto-refreshes every 30 seconds &mdash; {{ now()->format('d M Y, H:i:s') }}</p>
            </div>
            <span class="text-xs text-gray-500 bg-gray-800 px-3 py-1 rounded-full">/iclock/status</span>
        </div>

        {{-- Summary --}}
        <div class="mb-8 grid grid-cols-2 gap-4">
            <div class="bg-gray-900 border border-gray-800 rounded-xl p-5">
                <p class="text-xs uppercase tracking-widest text-gray-500">Devices checked in</p>
                <p class="text-3xl font-bold mt-1">{{ $devices->count() }}</p>
            </div>
            <div class="bg-gray-900 border border-gray-800 rounded-xl p-5">
                <p class="text-xs uppercase tracking-widest text-gray-500">Scans today</p>
                <p class="text-3xl font-bold mt-1">{{ $scansToday }}</p>
            </div>
        </div>

        {{-- Devices --}}
        <section>
            <h2 class="text-sm font-semibold uppercase tracking-widest text-gray-400 mb-3">Connected Devices</h2>

            @if ($devices->isEmpty())
                <div class="bg-gray-900 border border-gray-800 rounded-xl p-6 text-center text-gray-500">
                    No devices have checked in yet. Point your scanner at this server and it will appear here.
                </div>
            @else
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    @foreach ($devices as $device)
                        @php
                            $minutesAgo = $device->last_seen ? now()->diffInMinutes($device->last_seen) : null;
                            $isOnline   = $minutesAgo !== null && $minutesAgo < 5;
                            $lastLog    = $lastLogs[$device->sn] ?? null;
                        @endphp
                        <div class="bg-gray-900 border border-gray-800 rounded-xl p-5 flex items-start gap-4">
                            <span class="mt-1 inline-block w-2.5 h-2.5 rounded-full {{ $isOnline ? 'bg-green-400' : 'bg-gray-600' }}"></span>
                            <div class="space-y-1">
                                <p class="font-mono font-semibold text-white">{{ substr($device->sn, 0, 6) }}&hellip;</p>
                                @if ($device->name)
                                    <p class="text-xs text-gray-500">{{ $device->name }}</p>
                                @endif
                                <p class="text-sm text-gray-400">
                                    <span class="text-gray-600 text-xs uppercase tracking-wide">Last seen&nbsp;</span>
                                    {{ $device->last_seen ? $device->last_seen->diffForHumans() : 'Never' }}
                                </p>
                                <p class="text-sm text-gray-400">
                                    <span class="text-gray-600 text-xs uppercase tracking-wide">Last scan&nbsp;</span>
                                    @if ($lastLog)
                                        {{ $lastLog->scanned_at->diffForHumans() }}
                                        <span class="text-blue-400 font-mono">&nbsp;#{{ $lastLog->bio_metric_id }}</span>
                                    @else
                                        No scans yet
                                    @endif
                                </p>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </section>

    </div>

</body>
</html>
