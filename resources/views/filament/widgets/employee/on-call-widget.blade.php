<div>
    @php($onCall = $this->onCall())

    @if ($onCall)
        <x-filament::section>
            <x-slot name="heading">
                <span class="flex items-center gap-2">
                    <span>📞</span> On-call this week
                </span>
            </x-slot>

            <div class="flex items-center justify-between gap-3">
                <div class="min-w-0">
                    <p class="truncate text-base font-semibold text-gray-950 dark:text-white">
                        {{ $onCall['name'] }}
                        @if ($onCall['is_me'])
                            <span class="text-xs font-medium text-warning-600 dark:text-warning-400">(you)</span>
                        @endif
                    </p>
                    <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                        @if ($onCall['covering_for'])
                            Covering for {{ $onCall['covering_for'] }} (on leave) · {{ $onCall['range'] }}
                        @else
                            {{ $onCall['range'] }}
                        @endif
                    </p>
                </div>
                <span class="shrink-0 rounded-full bg-warning-50 px-2.5 py-1 text-xs font-medium text-warning-700 ring-1 ring-warning-600/20 dark:bg-warning-500/15 dark:text-warning-200 dark:ring-warning-400/30">
                    {{ $onCall['covering_for'] ? 'Stand-in' : 'Late dev' }}
                </span>
            </div>
        </x-filament::section>
    @endif
</div>
