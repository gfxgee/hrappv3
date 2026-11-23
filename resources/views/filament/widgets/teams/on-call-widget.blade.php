{{--
    The tab's single on-call surface. Same slot, same heading every week, so
    it becomes somewhere people look rather than something that appears at
    them — and when it is you, the card itself says so instead of a second
    banner repeating it higher up the page.
--}}
<div>
    @php($onCall = $this->onCall())

    @if ($onCall)
        @if ($onCall['is_me'])
            <section
                aria-labelledby="hr-on-call"
                class="rounded-3xl bg-yellow-50 px-5 pt-[18px] pb-5 shadow-[inset_0_0_0_1px_rgb(249_159_41/0.4),0_12px_28px_-18px_rgb(31_19_66/0.3)] dark:bg-yellow-400/10"
            >
                <h2 id="hr-on-call" class="mb-3 text-base font-bold tracking-[-0.2px] text-yellow-900 dark:text-yellow-200">
                    On-call this week
                </h2>

                <div class="flex items-center gap-[11px]">
                    <span
                        class="flex size-[38px] shrink-0 items-center justify-center rounded-xl bg-yellow-400/30 text-[17px]"
                        aria-hidden="true"
                    >📞</span>
                    <div class="min-w-0">
                        <p class="text-[14.5px] font-bold text-yellow-900 dark:text-yellow-100">
                            @if ($onCall['covering_for'])
                                You are covering today
                            @else
                                It's you this week
                            @endif
                        </p>
                        <p class="text-[12.5px] text-yellow-800 dark:text-yellow-200/80">{{ $onCall['range'] }}</p>
                    </div>
                </div>

                <p class="mt-3 border-t border-yellow-400/35 pt-[11px] text-[12.5px] text-yellow-800 dark:text-yellow-200/80">
                    @if ($onCall['covering_for'])
                        Standing in for {{ $onCall['covering_for'] }}, who is on leave. You are the go-to for urgent issues today.
                    @else
                        You are the go-to for urgent issues.
                    @endif
                </p>
            </section>
        @else
            <section
                aria-labelledby="hr-on-call"
                class="rounded-3xl bg-white px-5 pt-[18px] pb-5 shadow-[0_12px_28px_-18px_rgb(31_19_66/0.3)] dark:bg-white/5"
            >
                <h2 id="hr-on-call" class="mb-3 text-base font-bold tracking-[-0.2px] text-purple-950 dark:text-white">
                    On-call this week
                </h2>

                <div class="flex items-center gap-[11px]">
                    <span
                        class="flex size-[38px] shrink-0 items-center justify-center rounded-full bg-purple-700 text-[13px] font-bold text-white"
                        aria-hidden="true"
                    >{{ $this->initials($onCall['name']) }}</span>
                    <div class="min-w-0">
                        <p class="truncate text-[14.5px] font-bold text-purple-950 dark:text-white">{{ $onCall['name'] }}</p>
                        <p class="text-[12.5px] text-gray-900 dark:text-gray-400">
                            @if ($onCall['covering_for'])
                                Covering for {{ $onCall['covering_for'] }} · {{ $onCall['range'] }}
                            @else
                                {{ $onCall['range'] }}
                            @endif
                        </p>
                    </div>
                </div>
            </section>
        @endif
    @endif
</div>
