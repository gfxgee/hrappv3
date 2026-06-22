<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">File an Attendance Correction</x-slot>
        <x-slot name="description">
            Use this if a clock-in/out is missing or shows the wrong time. HR will
            review your request and update your attendance record.
        </x-slot>

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
        <x-slot name="heading">My Correction Requests</x-slot>

        {{ $this->table }}
    </x-filament::section>
</x-filament-panels::page>
