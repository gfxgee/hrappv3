@php
    $employee = $this->resolveEmployee();
    $data = $this->dtr();
    $rows = $data['rows'];
    $totals = $data['totals'];
@endphp

<x-filament-panels::page>
    {{-- Controls (hidden when printing) --}}
    <div class="flex flex-wrap items-end gap-3 print:hidden">
        @if ($this->canSelectEmployee())
            <div class="min-w-56">
                <label class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400">Employee</label>
                <select
                    wire:model.live="employeeId"
                    class="block w-full px-3 py-2 bg-white rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-white/20 dark:bg-white/5 dark:text-white"
                >
                    @foreach ($this->employeeOptions() as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </div>
        @endif

        <div>
            <label class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400">From</label>
            <input type="date" wire:model.live="from"
                   class="px-3 py-2 bg-white rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-white/20 dark:bg-white/5 dark:text-white" />
        </div>

        <div>
            <label class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400">Until</label>
            <input type="date" wire:model.live="until"
                   class="px-3 py-2 bg-white rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-white/20 dark:bg-white/5 dark:text-white" />
        </div>

        <div class="flex items-center gap-2">
            <x-filament::button size="sm" color="gray" outlined wire:click="thisMonth">This month</x-filament::button>
            <x-filament::button size="sm" color="gray" outlined wire:click="lastMonth">Last month</x-filament::button>
        </div>

        <div class="ml-auto flex items-center gap-2">
            <x-filament::button size="sm" color="gray" icon="heroicon-o-printer" x-on:click="window.print()">Print</x-filament::button>
            <x-filament::button size="sm" icon="heroicon-o-arrow-down-tray" wire:click="exportCsv">Export CSV</x-filament::button>
        </div>
    </div>

    {{-- Printable record --}}
    <div class="mt-4 rounded-xl border border-gray-200 bg-white p-5 dark:border-white/10 dark:bg-white/5 print:border-0 print:shadow-none">
        <div class="mb-4 flex flex-wrap items-end justify-between gap-2 border-b border-gray-100 pb-3 dark:border-white/10">
            <div>
                <h2 class="text-lg font-bold text-gray-950 dark:text-white">Daily Time Record</h2>
                <p class="text-sm text-gray-600 dark:text-gray-300">
                    {{ $employee?->name }}
                    @if ($employee?->department?->name)
                        <span class="text-gray-400">· {{ $employee->department->name }}</span>
                    @endif
                </p>
                <p class="text-xs text-gray-400">{{ $employee?->email }}</p>
            </div>
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $this->periodLabel() }}</p>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-200 text-left text-xs uppercase tracking-wide text-gray-500 dark:border-white/10 dark:text-gray-400">
                        <th class="py-2 pr-3">Date</th>
                        <th class="py-2 pr-3">Day</th>
                        <th class="py-2 pr-3">Time In</th>
                        <th class="py-2 pr-3">Time Out</th>
                        <th class="py-2 pr-3 text-right">Hours</th>
                        <th class="py-2 pr-3 text-right">Late</th>
                        <th class="py-2 pr-3 text-right">Undertime</th>
                        <th class="py-2 pr-3 text-right">OT</th>
                        <th class="py-2">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr @class([
                            'border-b border-gray-50 dark:border-white/5',
                            'bg-gray-50/50 dark:bg-white/[0.02]' => in_array($row['status'], ['Rest day', 'Holiday'], true),
                        ])>
                            <td class="py-1.5 pr-3 whitespace-nowrap text-gray-700 dark:text-gray-200">{{ $row['date']->format('M d') }}</td>
                            <td class="py-1.5 pr-3 text-gray-500 dark:text-gray-400">{{ $row['day'] }}</td>
                            <td class="py-1.5 pr-3 tabular-nums">{{ $row['time_in'] ?? '—' }}</td>
                            <td class="py-1.5 pr-3 tabular-nums">{{ $row['time_out'] ?? '—' }}</td>
                            <td class="py-1.5 pr-3 text-right tabular-nums">{{ $row['hours'] ?: '—' }}</td>
                            <td class="py-1.5 pr-3 text-right tabular-nums {{ $row['late'] ? 'text-warning-600 dark:text-warning-400' : 'text-gray-400' }}">{{ $row['late'] ? $this->humanMinutes($row['late']) : '—' }}</td>
                            <td class="py-1.5 pr-3 text-right tabular-nums {{ $row['undertime'] ? 'text-warning-600 dark:text-warning-400' : 'text-gray-400' }}">{{ $row['undertime'] ? $this->humanMinutes($row['undertime']) : '—' }}</td>
                            <td class="py-1.5 pr-3 text-right tabular-nums">{{ $row['overtime'] ?: '—' }}</td>
                            <td class="py-1.5">
                                <span @class([
                                    'rounded px-1.5 py-0.5 text-xs font-medium',
                                    'bg-success-100 text-success-700 dark:bg-success-500/20 dark:text-success-300' => $row['status'] === 'Present',
                                    'bg-danger-100 text-danger-700 dark:bg-danger-500/20 dark:text-danger-300' => $row['status'] === 'Absent',
                                    'bg-purple-100 text-purple-700 dark:bg-purple-500/20 dark:text-purple-200' => $row['status'] === 'Holiday',
                                    'bg-info-100 text-info-700 dark:bg-info-500/20 dark:text-info-300' => $row['status'] === 'Leave',
                                    'text-gray-400' => $row['status'] === 'Rest day',
                                ])>{{ $row['status'] }}</span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="border-t-2 border-gray-200 font-semibold dark:border-white/10">
                        <td class="py-2 pr-3" colspan="4">Totals</td>
                        <td class="py-2 pr-3 text-right tabular-nums">{{ $totals['hours'] }}</td>
                        <td class="py-2 pr-3 text-right tabular-nums">{{ $totals['late'] ? $this->humanMinutes($totals['late']) : '—' }}</td>
                        <td class="py-2 pr-3 text-right tabular-nums">{{ $totals['undertime'] ? $this->humanMinutes($totals['undertime']) : '—' }}</td>
                        <td class="py-2 pr-3 text-right tabular-nums">{{ $totals['overtime'] }}</td>
                        <td class="py-2 text-xs font-medium text-gray-500 dark:text-gray-400">
                            {{ $totals['present'] }} present · {{ $totals['absent'] }} absent · {{ $totals['leave'] }} leave
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <style>
        @media print {
            .fi-sidebar, .fi-topbar, .fi-sidebar-header { display: none !important; }
            .fi-main-ctn, .fi-main { padding: 0 !important; margin: 0 !important; max-width: 100% !important; }
        }
    </style>
</x-filament-panels::page>
