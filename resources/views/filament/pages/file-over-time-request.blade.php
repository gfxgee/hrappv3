<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">Overtime Summary</x-slot>
        <x-slot name="description">{{ now()->format('F Y') }}</x-slot>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div class="rounded-xl border border-gray-200 p-4 dark:border-white/10">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                    Approved this month
                </p>
                <p class="mt-1 text-2xl font-bold text-gray-950 dark:text-white">
                    {{ number_format($this->getApprovedHoursThisMonth(), 2) }} <span class="text-base font-normal text-gray-500">hrs</span>
                </p>
            </div>

            <div class="rounded-xl border border-gray-200 p-4 dark:border-white/10">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                    Pending approval
                </p>
                <p class="mt-1 text-2xl font-bold text-gray-950 dark:text-white">
                    {{ number_format($this->getPendingHours(), 2) }} <span class="text-base font-normal text-gray-500">hrs</span>
                </p>
            </div>
        </div>
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">File an Overtime Request</x-slot>

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
        <x-slot name="heading">My Overtime Requests</x-slot>

        {{ $this->table }}
    </x-filament::section>
</x-filament-panels::page>
