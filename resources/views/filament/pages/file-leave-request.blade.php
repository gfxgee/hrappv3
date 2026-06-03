<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">Leave Credits</x-slot>
        <x-slot name="description">Your available leave balances.</x-slot>

        @php($credits = $this->getLeaveCredits())

        @if (empty($credits))
            <p class="text-sm text-gray-500 dark:text-gray-400">
                No leave credits found. Ask HR to set up your leave balances.
            </p>
        @else
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                @foreach ($credits as $credit)
                    <div class="flex items-center gap-4 rounded-xl border border-gray-200 p-4 dark:border-white/10">
                        <div class="relative h-16 w-16 shrink-0">
                            <svg viewBox="0 0 36 36" class="h-16 w-16 -rotate-90">
                                <circle cx="18" cy="18" r="15.9155" fill="none"
                                    class="stroke-gray-200 dark:stroke-white/10" stroke-width="3.5" />
                                @if ($credit['tracked'])
                                    <circle cx="18" cy="18" r="15.9155" fill="none"
                                        stroke="{{ $credit['color'] }}" stroke-width="3.5" stroke-linecap="round"
                                        stroke-dasharray="{{ $credit['percent'] }} 100" />
                                @endif
                            </svg>
                            <span class="absolute inset-0 flex items-center justify-center text-base font-bold text-gray-950 dark:text-white">
                                {{ $credit['tracked'] ? $credit['remaining'] : $credit['used'] }}
                            </span>
                        </div>

                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold text-gray-950 dark:text-white">
                                {{ $credit['label'] }}
                            </p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                @if ($credit['tracked'])
                                    {{ $credit['used'] }} used of {{ $credit['total'] }} days
                                @else
                                    {{ $credit['used'] }} {{ \Illuminate\Support\Str::plural('day', $credit['used']) }} · no quota
                                @endif
                            </p>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">File a Leave Request</x-slot>

        <form wire:submit="create" class="space-y-6">
            {{ $this->form }}

            <div class="flex justify-end">
                <x-filament::button type="submit">
                    Submit Request
                </x-filament::button>
            </div>
        </form>
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">My Filed Leaves</x-slot>

        {{ $this->table }}
    </x-filament::section>
</x-filament-panels::page>
