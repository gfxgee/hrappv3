{{--
    The suggestion box, drawn for the Microsoft Teams tab.

    One narrow column, no sidebar. Crowding this page with widgets would
    undercut the only thing it has to say: nothing else is going on here.

    The card above the form lists the columns the row actually has. The
    anonymity is not a promise anyone has to take on trust — the suggestions
    table has no column for who sent it, so there is nothing to look up
    later, HR included. Saying that concretely is the point of the card.
--}}
<x-filament-panels::page>
    <x-teams.band
        title="Suggestion box"
        subtitle="Anonymous by construction. Only HR reads what you send."
    />

    <div class="mx-auto -mt-9 w-full max-w-[760px] space-y-3.5">
        @include('filament.announcements.teams-banners', ['inline' => true])

        {{-- The promise, stated as what gets stored --}}
        <section aria-labelledby="hr-sg-privacy" class="hr-card px-[22px] pt-5 pb-[22px]">
            <div class="flex items-center gap-[11px]">
                <span class="flex size-[38px] shrink-0 items-center justify-center rounded-[13px] bg-success-500/15 text-success-700 dark:text-success-300" aria-hidden="true">
                    <x-filament::icon icon="heroicon-o-lock-closed" class="size-5" />
                </span>
                <h2 id="hr-sg-privacy" class="text-base font-bold tracking-[-0.2px] text-purple-950 dark:text-white">
                    This is what gets saved
                </h2>
            </div>

            <div class="mt-3.5 rounded-2xl px-4 py-3.5" style="background: var(--hr-ground-soft)">
                @php
                    $stored = [
                        ['field' => 'Category', 'value' => 'The one you pick below, if any', 'good' => false],
                        ['field' => 'Message', 'value' => 'The words you type below', 'good' => false],
                        ['field' => 'Received', 'value' => now()->format('j F Y'), 'good' => false],
                        ['field' => 'Sent by', 'value' => 'Not recorded — there is no column for it', 'good' => true],
                    ];
                @endphp

                @foreach ($stored as $row)
                    <div class="flex items-baseline gap-3 py-[5px]">
                        <span class="w-[86px] shrink-0 text-[11px] font-bold tracking-[0.1em] uppercase" style="color: var(--hr-ink-soft)">
                            {{ $row['field'] }}
                        </span>
                        <span @class([
                            'min-w-0 text-[13.5px]',
                            'font-semibold text-success-700 dark:text-success-300' => $row['good'],
                            'text-purple-950 dark:text-white' => ! $row['good'],
                        ])>{{ $row['value'] }}</span>
                    </div>
                @endforeach
            </div>

            <p class="mt-3.5 text-[13.5px] leading-relaxed" style="color: var(--hr-ink-soft)">
                That is the whole record. Your name, your email and your employee number are never written down,
                so there is nothing for anyone to look up later — HR included.
            </p>
        </section>

        {{-- The form --}}
        <form wire:submit="submit" class="hr-card px-6 pt-[22px] pb-6">
            {{ $this->form }}

            <div class="mt-5 flex flex-wrap items-center gap-3.5 border-t pt-[18px]" style="border-color: var(--hr-ground)">
                <p class="min-w-0 flex-1 text-[13px]" style="color: var(--hr-ink-soft)">
                    You will not get a reply, because there is no one to reply to.
                </p>

                <button type="submit" class="hr-cta">Send anonymously</button>
            </div>
        </form>

        <p class="px-0.5 text-center text-[12.5px] leading-relaxed" style="color: var(--hr-ink-soft)">
            Need an answer to something? Message HR in Teams instead — that conversation has your name on it, which is the point.
        </p>
    </div>
</x-filament-panels::page>
