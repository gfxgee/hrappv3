<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">Share a suggestion</x-slot>
        <x-slot name="description">
            Your submission is completely anonymous — we don't record who you are.
            Only HR can read what you send. Use this to share ideas, concerns, or
            feedback you'd like the company to know about.
        </x-slot>

        <form wire:submit="submit" class="space-y-6">
            {{ $this->form }}

            <div class="flex justify-end">
                <x-filament::button type="submit">
                    Submit Anonymously
                </x-filament::button>
            </div>
        </form>
    </x-filament::section>
</x-filament-panels::page>
