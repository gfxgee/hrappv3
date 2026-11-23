{{--
    Filing overtime, drawn for the Microsoft Teams tab.

    Same shape as filing leave, and deliberately so: two forms that behave
    differently are two things to learn. The one addition is the card that
    tells people they need not come here at all — OT clock out on the
    dashboard files the same request and ends the shift in one step.
--}}
@php
    $approved = $this->getApprovedHoursThisMonth();
    $pending = $this->getPendingHours();

    $trim = fn (float $hours): string => rtrim(rtrim(number_format($hours, 2), '0'), '.');
@endphp

<x-filament-panels::page>
    <x-teams.band
        title="File overtime"
        subtitle="Hours you worked beyond your shift. Only approved hours are paid."
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
                <section aria-labelledby="hr-ot-month" data-reveal class="hr-card px-5 py-[18px]">
                    <h2 id="hr-ot-month" class="mb-3 text-base font-bold tracking-[-0.2px] text-purple-950 dark:text-white">
                        {{ now()->format('F') }} so far
                    </h2>

                    <div class="flex items-baseline gap-2.5">
                        <span
                            class="text-[34px] leading-none font-bold tracking-[-1.4px] tabular-nums text-purple-950 dark:text-white"
                            data-count-to="{{ $approved }}"
                            data-count-places="{{ floor($approved) == $approved ? 0 : 1 }}"
                        >{{ $trim($approved) }}</span>
                        <span class="text-[13.5px]" style="color: var(--hr-ink-soft)">hours approved</span>
                    </div>

                    <p class="mt-1.5 text-[13px] font-semibold" style="color: {{ $pending > 0 ? '#912c0f' : 'var(--hr-ink-soft)' }}">
                        @if ($pending > 0)
                            {{ $trim($pending) }} {{ Str::plural('hour', $pending) }} still awaiting approval.
                        @else
                            Nothing awaiting approval.
                        @endif
                    </p>
                </section>

                <section aria-labelledby="hr-ot-tip" class="rounded-[20px] px-5 pt-4 pb-[18px]" style="box-shadow: inset 0 0 0 1px var(--hr-hairline)">
                    <h2 id="hr-ot-tip" class="mb-1 text-[15px] font-bold tracking-[-0.2px] text-purple-950 dark:text-white">Next time, skip this form</h2>
                    <p class="text-[13px]" style="color: var(--hr-ink-soft)">
                        Use <strong class="font-bold">OT Clock Out</strong> on the dashboard. It files the request and ends your shift in one step, with the date already filled in.
                    </p>
                </section>

                <x-teams.next-steps :steps="[
                    'Your approver gets it in Teams straight away.',
                    'You will see it move to Approved or Declined in the list below.',
                    'Approved hours count towards the total above.',
                ]" />
            </div>
        </div>

        <section aria-labelledby="hr-ot-filed" class="hr-card px-[22px] pt-5 pb-2">
            <h2 id="hr-ot-filed" class="mb-3 text-base font-bold tracking-[-0.2px] text-purple-950 dark:text-white">My overtime requests</h2>

            {{ $this->table }}
        </section>
    </div>
</x-filament-panels::page>
