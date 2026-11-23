{{--
    The Daily Time Record, drawn for the Microsoft Teams tab.

    Same page object and the same figures as /admin — this is the design's
    running order, not a second record. The difference worth naming: the
    month's shape comes first, as four numbers, and the day-by-day table
    follows. People open this to answer "how am I doing?" far more often
    than "what time did I clock in on the 11th?", and the old layout made
    them read 20 rows to find out.
--}}
@php
    $employee = $this->resolveEmployee();
    $data = $this->dtr();
    $rows = $data['rows'];
    $totals = $data['totals'];

    $shifts = collect($rows)->filter(fn (array $row): bool => filled($row['time_in']))->count();
    $lateAndUndertime = (int) $totals['late'] + (int) $totals['undertime'];

    $trim = fn (float $hours): string => rtrim(rtrim(number_format($hours, 2), '0'), '.');
@endphp

<x-filament-panels::page>
    <x-teams.band
        title="Daily Time Record"
        :subtitle="trim(($employee?->name ?? '').' — '.$this->periodLabel(), ' —')"
    >
        <div class="flex flex-wrap items-center gap-2.5 print:hidden">
            <a
                href="{{ route('dtr.pdf', ['employee' => $this->employeeId, 'from' => $this->from, 'until' => $this->until]) }}"
                target="_blank"
                class="hr-btn border-0 bg-white/10 text-white shadow-none hover:bg-white/15"
            >
                <x-filament::icon icon="heroicon-o-printer" class="size-4" />
                Print / PDF
            </a>

            <button type="button" wire:click="exportCsv" class="hr-cta h-11">
                <x-filament::icon icon="heroicon-o-arrow-down-tray" class="size-4" />
                Export CSV
            </button>
        </div>
    </x-teams.band>

    <div class="mx-auto -mt-9 w-full max-w-[1180px] space-y-3.5">
        @include('filament.announcements.teams-banners', ['inline' => true])

        {{-- Period controls --}}
        <div class="hr-card flex flex-wrap items-end gap-3.5 px-5 py-4 print:hidden">
            @if ($this->canSelectEmployee())
                <div class="min-w-0 flex-[1_1_220px]">
                    <label for="hr-dtr-who" class="hr-label">Employee</label>
                    <select id="hr-dtr-who" wire:model.live="employeeId" class="hr-field">
                        @foreach ($this->employeeOptions() as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
            @endif

            <div class="flex-[0_1_160px]">
                <label for="hr-dtr-from" class="hr-label">From</label>
                <input id="hr-dtr-from" type="date" wire:model.live="from" class="hr-field" />
            </div>

            <div class="flex-[0_1_160px]">
                <label for="hr-dtr-until" class="hr-label">Until</label>
                <input id="hr-dtr-until" type="date" wire:model.live="until" class="hr-field" />
            </div>

            <div class="flex gap-2">
                <button type="button" wire:click="thisMonth" class="hr-btn hr-btn-on">This month</button>
                <button type="button" wire:click="lastMonth" class="hr-btn">Last month</button>
            </div>
        </div>

        {{-- The month at a glance, before the detail --}}
        <div data-reveal class="grid grid-cols-2 gap-3.5 lg:grid-cols-4">
            @php
                $summary = [
                    [
                        'label' => 'Days present',
                        'value' => (string) $totals['present'],
                        'count' => (float) $totals['present'],
                        'places' => 0,
                        'note' => $totals['leave'].' '.Str::plural('day', $totals['leave']).' of leave · '.$totals['absent'].' absent',
                        'ink' => null,
                    ],
                    [
                        'label' => 'Hours worked',
                        'value' => (string) $totals['hours'],
                        'count' => (float) $totals['hours'],
                        'places' => 1,
                        'note' => 'Across '.$shifts.' '.Str::plural('shift', $shifts),
                        'ink' => null,
                    ],
                    [
                        'label' => 'Late and undertime',
                        'value' => $lateAndUndertime ? $this->humanMinutes($lateAndUndertime) : '—',
                        'count' => null,
                        'places' => 0,
                        'note' => $lateAndUndertime
                            ? $this->humanMinutes((int) $totals['late']).' late, '.$this->humanMinutes((int) $totals['undertime']).' undertime'
                            : 'On time every shift',
                        'ink' => $lateAndUndertime ? '#b2390b' : null,
                    ],
                    [
                        'label' => 'Overtime filed',
                        'value' => $trim((float) $totals['overtime']).' h',
                        'count' => null,
                        'places' => 0,
                        'note' => $totals['overtime_pending'] > 0
                            ? $trim((float) $totals['overtime_pending']).' h awaiting approval'
                            : 'Nothing awaiting approval',
                        'ink' => $totals['overtime_pending'] > 0 ? '#912c0f' : null,
                    ],
                ];
            @endphp

            @foreach ($summary as $stat)
                <div class="hr-card px-[18px] pt-[15px] pb-4">
                    <div class="text-[12.5px] font-semibold" style="color: var(--hr-ink-soft)">{{ $stat['label'] }}</div>

                    <div
                        class="mt-1 text-[27px] leading-none font-bold tracking-[-1px] tabular-nums text-purple-950 dark:text-white"
                        @if ($stat['count'] !== null)
                            data-count-to="{{ $stat['count'] }}"
                            data-count-places="{{ $stat['places'] }}"
                        @endif
                    >{{ $stat['value'] }}</div>

                    <div class="mt-1 text-[12.5px]" style="color: {{ $stat['ink'] ?? 'var(--hr-ink-soft)' }}">{{ $stat['note'] }}</div>
                </div>
            @endforeach
        </div>

        {{-- The record --}}
        <section aria-labelledby="hr-dtr-table" id="dtr-printable" class="hr-card overflow-x-auto px-[22px] pt-5 pb-2 print:shadow-none">
            <h2 id="hr-dtr-table" class="mb-3 text-base font-bold tracking-[-0.2px] text-purple-950 dark:text-white">Day by day</h2>

            <table class="w-full min-w-[920px] border-collapse text-left">
                <thead>
                    <tr class="border-b-[1.5px] border-[color:var(--hr-ground)]">
                        @foreach (['Date', 'Day', 'Time in', 'Time out'] as $column)
                            <th scope="col" class="pr-2.5 pb-2.5 text-[11px] font-bold tracking-[0.1em] uppercase" style="color: var(--hr-ink-soft)">{{ $column }}</th>
                        @endforeach
                        @foreach (['Hours', 'Late', 'Undertime'] as $column)
                            <th scope="col" class="pr-2.5 pb-2.5 text-right text-[11px] font-bold tracking-[0.1em] uppercase" style="color: var(--hr-ink-soft)">{{ $column }}</th>
                        @endforeach
                        <th scope="col" class="pr-2.5 pb-2.5 pl-5 text-[11px] font-bold tracking-[0.1em] uppercase" style="color: var(--hr-ink-soft)">Overtime</th>
                        <th scope="col" class="w-[104px] pb-2.5 text-[11px] font-bold tracking-[0.1em] uppercase" style="color: var(--hr-ink-soft)">Status</th>
                    </tr>
                </thead>

                <tbody>
                    @foreach ($rows as $row)
                        @php($quiet = in_array($row['status'], ['Rest day', 'Holiday'], true))

                        <tr @class([
                            'border-b border-[color:var(--hr-ground-soft)]',
                            'bg-[color:var(--hr-ground-soft)]' => $quiet,
                        ])>
                            <td @class(['py-[11px] pr-2.5 text-[13.5px] font-semibold whitespace-nowrap tabular-nums', 'text-purple-950 dark:text-white' => ! $quiet, 'opacity-55' => $quiet])>{{ $row['date']->format('j M') }}</td>
                            <td @class(['py-[11px] pr-2.5 text-[13.5px]', 'opacity-55' => $quiet])  style="color: var(--hr-ink-soft)">{{ $row['day'] }}</td>
                            <td @class(['py-[11px] pr-2.5 text-[13.5px] tabular-nums', 'text-purple-950 dark:text-white' => ! $quiet, 'opacity-55' => $quiet])>{{ $row['time_in'] ?: '—' }}</td>
                            <td @class(['py-[11px] pr-2.5 text-[13.5px] whitespace-nowrap tabular-nums', 'text-purple-950 dark:text-white' => ! $quiet, 'opacity-55' => $quiet])>
                                {{ $row['time_out'] ?: '—' }}
                                @if ($row['overnight'])
                                    <span class="ml-1 rounded px-1 text-[10px] font-semibold" style="background: var(--hr-ground); color: var(--hr-ink-soft)" title="Clocked out the next day">+1</span>
                                @endif
                            </td>
                            <td @class(['py-[11px] pr-2.5 text-right text-[13.5px] font-semibold tabular-nums', 'text-purple-950 dark:text-white' => ! $quiet, 'opacity-55' => $quiet])>{{ $row['hours'] ?: '—' }}</td>
                            <td class="py-[11px] pr-2.5 text-right text-[13.5px] tabular-nums" style="color: {{ $row['late'] ? '#b2390b' : 'var(--hr-ink-soft)' }}">{{ $row['late'] ? $this->humanMinutes($row['late']) : '—' }}</td>
                            <td class="py-[11px] pr-2.5 text-right text-[13.5px] tabular-nums" style="color: {{ $row['undertime'] ? '#b2390b' : 'var(--hr-ink-soft)' }}">{{ $row['undertime'] ? $this->humanMinutes($row['undertime']) : '—' }}</td>
                            <td class="py-[11px] pr-2.5 pl-5">
                                @if ($row['overtime_breakdown'] === [])
                                    <span class="text-[13.5px]" style="color: var(--hr-ink-soft)">—</span>
                                @else
                                    <span class="inline-flex flex-wrap items-center gap-1">
                                        @foreach ($row['overtime_breakdown'] as $entry)
                                            <x-filament::badge :color="$entry['status']->color()" :title="$entry['status']->label()">
                                                {{ $trim((float) $entry['hours']) }}h
                                            </x-filament::badge>
                                        @endforeach
                                    </span>
                                @endif
                            </td>
                            <td class="py-[11px]">
                                <span @class([
                                    'inline-block rounded-full px-[11px] py-[3px] text-[12.5px] font-bold',
                                    'bg-success-100 text-success-700 dark:bg-success-500/20 dark:text-success-300' => $row['status'] === 'Present',
                                    'bg-danger-100 text-danger-700 dark:bg-danger-500/20 dark:text-danger-300' => $row['status'] === 'Absent',
                                    'bg-purple-100 text-purple-700 dark:bg-purple-500/20 dark:text-purple-200' => $row['status'] === 'Holiday',
                                    'bg-info-100 text-info-700 dark:bg-info-500/20 dark:text-info-300' => $row['status'] === 'Leave',
                                    'bg-[color:var(--hr-ground)]' => $row['status'] === 'Rest day',
                                ])
                                @if ($row['status'] === 'Rest day') style="color: var(--hr-ink-soft)" @endif
                                >{{ $row['status'] }}</span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>

                <tfoot>
                    <tr>
                        <td colspan="4" class="py-4 pr-2.5 text-[13.5px] font-bold text-purple-950 dark:text-white">Totals</td>
                        <td class="py-4 pr-2.5 text-right text-[15px] font-bold tabular-nums text-purple-950 dark:text-white">{{ $totals['hours'] }}</td>
                        <td class="py-4 pr-2.5 text-right text-[13.5px] font-bold tabular-nums" style="color: {{ $totals['late'] ? '#b2390b' : 'var(--hr-ink-soft)' }}">{{ $totals['late'] ? $this->humanMinutes($totals['late']) : '—' }}</td>
                        <td class="py-4 pr-2.5 text-right text-[13.5px] font-bold tabular-nums" style="color: {{ $totals['undertime'] ? '#b2390b' : 'var(--hr-ink-soft)' }}">{{ $totals['undertime'] ? $this->humanMinutes($totals['undertime']) : '—' }}</td>
                        <td class="py-4 pr-2.5 pl-5 text-[13.5px] font-bold whitespace-nowrap tabular-nums text-purple-950 dark:text-white">
                            {{ $totals['overtime'] }} h
                            @if ($totals['overtime_pending'] > 0)
                                <x-filament::badge color="info" title="Overtime awaiting approval">
                                    +{{ $trim((float) $totals['overtime_pending']) }}h pending
                                </x-filament::badge>
                            @endif
                        </td>
                        <td class="py-4 text-[12px] font-semibold" style="color: var(--hr-ink-soft)">
                            {{ $totals['present'] }} present · {{ $totals['absent'] }} absent · {{ $totals['leave'] }} leave
                        </td>
                    </tr>
                </tfoot>
            </table>
        </section>

        <p class="px-0.5 text-[12.5px]" style="color: var(--hr-ink-soft)">
            Rest days and holidays are shaded. Overtime shows as filed — only approved hours are paid.
        </p>
    </div>

    <style>
        @media print {
            /* Filament renders .fi-main-ctn at opacity 0 and fades it in via a
               transition; the print clone keeps the initial state, printing a
               blank page. Force it visible and undo layout clipping. */
            .fi-main-ctn {
                opacity: 1 !important;
                transition: none !important;
            }

            html, body, .fi-body, .fi-layout, .fi-main-ctn, .fi-main {
                height: auto !important;
                min-height: 0 !important;
                overflow: visible !important;
            }

            .fi-sidebar, .fi-topbar, .fi-sidebar-header, .hr-bleed { display: none !important; }

            .fi-main-ctn, .fi-main { padding: 0 !important; margin: 0 !important; max-width: 100% !important; width: 100% !important; }

            .fi-body { background: #fff !important; }

            /* Chrome's print snapshot often skips loading the web font, leaving
               glyphs unpainted (text shows as blank bars). Force a system font
               and solid dark text so the record is always legible. */
            #dtr-printable, #dtr-printable * {
                font-family: system-ui, -apple-system, "Segoe UI", Roboto, Arial, sans-serif !important;
                color: #111827 !important;
                -webkit-text-fill-color: #111827 !important;
            }
        }
    </style>
</x-filament-panels::page>
