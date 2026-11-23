{{--
    Filing a leave request, drawn for the Microsoft Teams tab.

    The form is still Filament's — the same fields, the same validation, the
    same credit-balance rule — so there is one definition of what a valid
    request is. What changes is everything around it: the balance you are
    spending sits beside the form while you fill it in rather than above it,
    and the page says what happens after you press the button.
--}}
@php($credits = $this->getLeaveCredits())

<x-filament-panels::page>
    <x-teams.band
        title="File a leave"
        subtitle="Your approver is notified in Teams the moment you file it."
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

            {{-- What you need to know while filling it in --}}
            <div class="space-y-3.5">
                <section aria-labelledby="hr-lv-balance" class="hr-card px-5 py-[18px]">
                    <h2 id="hr-lv-balance" class="mb-2.5 text-base font-bold tracking-[-0.2px] text-purple-950 dark:text-white">Your balance</h2>

                    @if ($credits === [])
                        <p class="text-[13px]" style="color: var(--hr-ink-soft)">
                            No leave credits found. Ask HR to set up your balances.
                        </p>
                    @else
                        @foreach ($credits as $credit)
                            <div class="flex items-center gap-[11px] py-2">
                                <span
                                    class="size-2.5 shrink-0 rounded-full"
                                    style="background: {{ $credit['tracked'] ? $credit['color'] : 'var(--hr-field-line)' }}"
                                    aria-hidden="true"
                                ></span>

                                <span class="min-w-0 flex-1 truncate text-[13.5px] font-semibold text-purple-950 dark:text-white">
                                    {{ $credit['label'] }}
                                </span>

                                <span class="shrink-0 text-[13.5px] font-bold tabular-nums text-purple-950 dark:text-white">
                                    @if ($credit['tracked'])
                                        {{ $credit['remaining'] }}<span class="font-semibold" style="color: var(--hr-ink-soft)"> of {{ $credit['total'] }}</span>
                                    @else
                                        {{ $credit['used'] }}<span class="font-semibold" style="color: var(--hr-ink-soft)"> used</span>
                                    @endif
                                </span>
                            </div>
                        @endforeach

                        <p class="mt-2.5 border-t pt-[11px] text-[12.5px]" style="border-color: var(--hr-ground); color: var(--hr-ink-soft)">
                            Days remaining this year. Types without a quota show what you have used.
                        </p>
                    @endif
                </section>

                <x-teams.next-steps :steps="[
                    'Your approver gets it in Teams straight away.',
                    'You will see it move to Approved or Declined in the list below.',
                    'Approved days come off the balance above.',
                ]" />
            </div>
        </div>

        {{-- Everything you have filed --}}
        <section aria-labelledby="hr-lv-filed" class="hr-card px-[22px] pt-5 pb-2">
            <h2 id="hr-lv-filed" class="mb-3 text-base font-bold tracking-[-0.2px] text-purple-950 dark:text-white">My filed leaves</h2>

            {{ $this->table }}
        </section>
    </div>
</x-filament-panels::page>
