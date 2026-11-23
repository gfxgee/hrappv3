{{--
    The figures carry their finished values in the markup. teams-motion.ts
    counts them up from zero on arrival where motion is welcome; everywhere
    else — reduced motion, a backgrounded tab, no JavaScript — this is already
    what it should say.
--}}
<div data-reveal>
    @php($stats = $this->stats())

    @if ($stats !== [])
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            @foreach ($stats as $stat)
                <div
                    class="rounded-[20px] bg-white px-[18px] pt-4 pb-[18px] shadow-[0_12px_28px_-18px_rgb(31_19_66/0.3)] dark:bg-white/5"
                >
                    <div class="flex items-center gap-2.5">
                        <span
                            class="flex size-[34px] shrink-0 items-center justify-center rounded-xl text-base"
                            style="background: {{ $stat['tile'] }}"
                            aria-hidden="true"
                        >{{ $stat['glyph'] }}</span>
                        <span class="min-w-0 grow text-[12.5px] font-bold text-gray-900 dark:text-gray-300">
                            {{ $stat['label'] }}
                        </span>
                    </div>

                    <p class="mt-[13px] flex items-baseline gap-[7px]">
                        <span
                            class="text-[33px] leading-none font-bold tracking-[-1.4px] tabular-nums text-purple-950 dark:text-white"
                            data-count-to="{{ $stat['count'] }}"
                            data-count-places="{{ $stat['places'] }}"
                        >{{ $stat['value'] }}</span>
                        <span class="text-[13px] font-semibold text-gray-900 dark:text-gray-400">{{ $stat['unit'] }}</span>
                    </p>

                    {{-- The bar carries the ratio the note states, so the figure
                         can be read at a glance instead of divided in your head. --}}
                    <div class="mt-[13px] flex h-[7px] overflow-hidden rounded bg-beige-100 dark:bg-white/10">
                        <span
                            style="width: {{ $stat['fill'] }}%; background: {{ $stat['accent'] }}"
                            data-grow-to="{{ $stat['fill'] }}"
                        ></span>
                        @if ($stat['accent2'])
                            <span
                                style="width: {{ $stat['fill2'] }}%; background: {{ $stat['accent2'] }}"
                                data-grow-to="{{ $stat['fill2'] }}"
                            ></span>
                        @endif
                    </div>

                    <p class="mt-[9px] text-[12.5px] text-gray-900 dark:text-gray-400">{{ $stat['note'] }}</p>
                </div>
            @endforeach
        </div>
    @endif
</div>
