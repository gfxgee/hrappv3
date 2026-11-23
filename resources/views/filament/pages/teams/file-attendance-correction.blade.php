{{--
    Filing an attendance correction, drawn for the Microsoft Teams tab.

    There is no mockup for this screen, so it follows the two forms that do
    have one — leave and overtime — rather than inventing a third shape. Two
    forms that behave differently are two things to learn; three would be
    worse.

    The one thing it says that the others do not: this is the page for fixing
    the record, not for explaining an absence. People reach for it when they
    have forgotten to clock out, and the sidebar says so before they type.
--}}
<x-filament-panels::page>
    <x-teams.band
        title="Fix my attendance"
        subtitle="A punch that is missing, or one that shows the wrong time."
    />

    <div class="mx-auto -mt-9 w-full max-w-[1180px] space-y-3.5">
        @include('filament.announcements.teams-banners', ['inline' => true])

        <div class="grid grid-cols-1 items-start gap-3.5 lg:grid-cols-3">
            {{-- The form --}}
            <form wire:submit="create" class="hr-card px-[26px] pt-6 pb-[26px] lg:col-span-2">
                {{ $this->form }}

                <div class="mt-[22px] flex flex-wrap items-center justify-end gap-3.5 border-t pt-5" style="border-color: var(--hr-ground)">
                    <button type="submit" class="hr-cta">File request</button>
                </div>
            </form>

            <div class="space-y-3.5">
                <section aria-labelledby="hr-ac-what" class="hr-card px-5 py-[18px]">
                    <h2 id="hr-ac-what" class="mb-2.5 text-base font-bold tracking-[-0.2px] text-purple-950 dark:text-white">
                        Use this when
                    </h2>

                    @php
                        $cases = [
                            'You forgot to clock in or out.',
                            'The scanner recorded the wrong time.',
                            'A punch is missing from your record entirely.',
                        ];
                    @endphp

                    @foreach ($cases as $case)
                        <div class="flex items-start gap-[11px] py-[7px]">
                            <x-filament::icon icon="heroicon-o-check-circle" class="mt-px size-[18px] shrink-0 text-success-600 dark:text-success-400" />
                            <span class="min-w-0 text-[13.5px] leading-snug text-purple-950 dark:text-white">{{ $case }}</span>
                        </div>
                    @endforeach

                    <p class="mt-2.5 border-t pt-[11px] text-[12.5px] leading-relaxed" style="border-color: var(--hr-ground); color: var(--hr-ink-soft)">
                        Taking the day off instead? File leave, not a correction — a correction changes what the record says happened.
                    </p>
                </section>

                <x-teams.next-steps :steps="[
                    'HR reviews it against the scanner log.',
                    'You will see it move to Approved or Declined in the list below.',
                    'An approved correction updates your Daily Time Record.',
                ]" />
            </div>
        </div>

        <section aria-labelledby="hr-ac-filed" class="hr-card px-[22px] pt-5 pb-2">
            <h2 id="hr-ac-filed" class="mb-3 text-base font-bold tracking-[-0.2px] text-purple-950 dark:text-white">My correction requests</h2>

            {{ $this->table }}
        </section>
    </div>
</x-filament-panels::page>
