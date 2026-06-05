@php
    use App\Enum\LeaveType;
@endphp

@if (! empty($holiday))
    <div class="mb-3 flex items-center gap-2 rounded-lg bg-purple-100 px-3 py-2 text-sm font-semibold text-purple-800 dark:bg-purple-500/20 dark:text-purple-200">
        <span>🎉</span>
        <span>{{ $holiday->name }}</span>
    </div>
@endif

@if ($leaves->isEmpty())
    <p class="py-6 text-center text-sm text-gray-500 dark:text-gray-400">
        No leaves on this day.
    </p>
@else
    <div class="space-y-2">
        @foreach ($leaves as $leave)
            @php($type = $leave->request_type)
            @php($recipient = $leave->user)
            @php($avatar = $recipient?->getFilamentAvatarUrl()
                ?? 'https://ui-avatars.com/api/?name=' . urlencode($recipient?->name ?? 'User') . '&size=80')

            <div class="flex items-start gap-3 rounded-lg border border-gray-100 p-2.5 dark:border-white/10">
                <img src="{{ $avatar }}" alt="{{ $recipient?->name }}"
                     class="h-9 w-9 shrink-0 rounded-full object-cover" />

                <div class="min-w-0 flex-1">
                    <div class="flex items-center justify-between gap-2">
                        <p class="truncate text-sm font-semibold text-gray-950 dark:text-white">
                            {{ $recipient?->name ?? 'Unknown' }}
                        </p>
                        <span @class([
                            'shrink-0 rounded-full px-2 py-0.5 text-[11px] font-medium',
                            $this->chipClasses($leave),
                        ])>
                            {{ $leave->status?->label() }}
                        </span>
                    </div>

                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        {{ $recipient?->department?->name ?? 'No department' }}
                    </p>

                    <p class="mt-1 text-sm text-gray-700 dark:text-gray-300">
                        {{ $type instanceof LeaveType ? $type->label() : 'Leave' }}
                        <span class="text-gray-400">·</span>
                        {{ $leave->start_date?->format('M j') }}@if ($leave->start_date?->ne($leave->end_date)) – {{ $leave->end_date?->format('M j, Y') }}@else, {{ $leave->start_date?->format('Y') }}@endif
                    </p>

                    @if (filled($leave->reason))
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $leave->reason }}</p>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
@endif
