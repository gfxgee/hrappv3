@php
    use App\Enum\LeaveType;
    use Illuminate\Support\Str;
@endphp

<x-filament-panels::page>
    {{-- Filters --}}
    <div class="flex flex-wrap items-end gap-3">
        <div class="min-w-48">
            <label class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400">Department</label>
            <select
                wire:model.live="departmentId"
                class="block w-full px-4 py-2 rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-white/20 dark:bg-white/5 dark:text-white"
            >
                <option value="">All departments</option>
                @foreach ($this->departmentOptions() as $id => $name)
                    <option value="{{ $id }}">{{ $name }}</option>
                @endforeach
            </select>
        </div>

        <div class="min-w-48">
            <label class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400">Leave type</label>
            <select
                wire:model.live="leaveType"
                class="block w-full px-4 py-2 rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-white/20 dark:bg-white/5 dark:text-white"
            >
                <option value="">All types</option>
                @foreach ($this->leaveTypeOptions() as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div class="min-w-40">
            <label class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400">Status</label>
            <select
                wire:model.live="status"
                class="block w-full px-4 py-2 rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-white/20 dark:bg-white/5 dark:text-white"
            >
                <option value="">All (active)</option>
                @foreach ($this->statusOptions() as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>
    </div>

    {{-- Month navigation --}}
    <div class="mt-4 flex items-center justify-between">
        <h2 class="text-lg font-semibold text-gray-950 dark:text-white">{{ $this->getMonthLabel() }}</h2>

        <div class="flex items-center gap-1">
            <x-filament::button size="sm" color="gray" outlined wire:click="previousMonth" icon="heroicon-m-chevron-left" />
            <x-filament::button size="sm" color="gray" outlined wire:click="goToToday">Today</x-filament::button>
            <x-filament::button size="sm" color="gray" outlined wire:click="nextMonth" icon="heroicon-m-chevron-right" />
        </div>
    </div>

    {{-- Calendar grid --}}
    <div class="mt-3 overflow-hidden rounded-xl border border-gray-200 dark:border-white/10">
        {{-- Weekday header --}}
        <div class="grid grid-cols-7 border-b border-gray-200 bg-gray-50 text-center text-xs font-semibold uppercase tracking-wide text-gray-500 dark:border-white/10 dark:bg-white/5 dark:text-gray-400">
            @foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $weekday)
                <div class="py-2">{{ $weekday }}</div>
            @endforeach
        </div>

        {{-- Days --}}
        <div class="grid grid-cols-7">
            @foreach ($this->getCalendarWeeks() as $week)
                @foreach ($week as $day)
                    @php($dateKey = $day['date']->toDateString())
                    <div @class([
                        'min-h-28 border-b border-r border-gray-100 p-1.5 dark:border-white/5',
                        'bg-gray-50/60 dark:bg-white/[0.02]' => ! $day['inMonth'] && ! $day['holiday'],
                        'bg-gray-50/40 dark:bg-white/[0.02]' => $day['inMonth'] && $day['isWeekend'] && ! $day['holiday'],
                        'bg-purple-50/70 dark:bg-purple-500/10' => (bool) $day['holiday'],
                    ])>
                        <div class="flex items-center justify-between">
                            <button
                                type="button"
                                wire:click="mountAction('dayDetail', @js(['date' => $dateKey]))"
                                @class([
                                    'flex h-6 w-6 items-center justify-center rounded-full text-xs font-medium transition hover:bg-gray-200 dark:hover:bg-white/10',
                                    'bg-primary-600 text-white hover:bg-primary-500' => $day['isToday'],
                                    'text-gray-400' => ! $day['inMonth'] && ! $day['isToday'],
                                    'text-gray-700 dark:text-gray-300' => $day['inMonth'] && ! $day['isToday'],
                                ])
                            >
                                {{ $day['date']->day }}
                            </button>

                            @if ($day['leaves']->isNotEmpty())
                                <span class="text-[10px] font-medium text-gray-400">{{ $day['leaves']->count() }}</span>
                            @endif
                        </div>

                        @if ($day['holiday'])
                            <button
                                type="button"
                                wire:click="mountAction('dayDetail', @js(['date' => $dateKey]))"
                                title="{{ $day['holiday']->name }}"
                                class="mt-1 flex w-full items-center gap-1 truncate rounded bg-purple-100 px-1.5 py-0.5 text-left text-[11px] font-semibold text-purple-800 dark:bg-purple-500/20 dark:text-purple-200"
                            >
                                <span>🎉</span>
                                <span class="truncate">{{ $day['holiday']->name }}</span>
                            </button>
                        @endif

                        <div class="mt-1 space-y-1">
                            @foreach ($day['leaves']->take(3) as $leave)
                                @php($type = $leave->request_type)
                                <button
                                    type="button"
                                    wire:key="cal-{{ $dateKey }}-{{ $leave->id }}"
                                    wire:click="mountAction('dayDetail', @js(['date' => $dateKey]))"
                                    title="{{ $leave->user?->name }} — {{ $type instanceof LeaveType ? $type->plainLabel() : '' }} ({{ $leave->start_date?->format('M j') }}–{{ $leave->end_date?->format('M j') }})"
                                    @class(['flex w-full items-center gap-1 truncate rounded px-1.5 py-0.5 text-left text-[11px] font-medium', $this->chipClasses($leave)])
                                >
                                    <span>{{ $type instanceof LeaveType ? $type->icon() : '🗓️' }}</span>
                                    <span class="truncate">{{ Str::of($leave->user?->name ?? 'Unknown')->before(' ') ?: $leave->user?->name }}</span>
                                </button>
                            @endforeach

                            @if ($day['leaves']->count() > 3)
                                <button
                                    type="button"
                                    wire:click="mountAction('dayDetail', @js(['date' => $dateKey]))"
                                    class="w-full rounded px-1.5 py-0.5 text-left text-[11px] font-medium text-primary-600 hover:underline dark:text-primary-400"
                                >
                                    +{{ $day['leaves']->count() - 3 }} more
                                </button>
                            @endif
                        </div>
                    </div>
                @endforeach
            @endforeach
        </div>
    </div>

    {{-- Legend --}}
    <div class="mt-3 flex flex-wrap items-center gap-4 text-xs text-gray-500 dark:text-gray-400">
        <span class="inline-flex items-center gap-1.5">
            <span class="h-3 w-3 rounded-full bg-success-400"></span> Approved / Verified
        </span>
        <span class="inline-flex items-center gap-1.5">
            <span class="h-3 w-3 rounded-full bg-warning-400"></span> For Approval
        </span>
        <span class="inline-flex items-center gap-1.5">
            <span class="h-3 w-3 rounded-full bg-purple-400"></span> Holiday
        </span>
    </div>

    <x-filament-actions::modals />
</x-filament-panels::page>
