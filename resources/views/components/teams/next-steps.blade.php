{{--
    What happens after you press the button.

    Filing a request is the moment people most want to know they can stop
    thinking about it. Three numbered lines is the whole card.
--}}
@props([
    'heading' => 'What happens next',
    'steps' => [],
])

<section aria-labelledby="{{ $id = 'hr-next-'.Str::random(6) }}" class="rounded-[24px] bg-purple-950 px-5 pt-[18px] pb-5 text-white shadow-[0_12px_28px_-16px_rgb(31_19_66/0.5)]">
    <h2 id="{{ $id }}" class="mb-2.5 text-base font-bold tracking-[-0.2px]">{{ $heading }}</h2>

    @foreach ($steps as $index => $step)
        <div class="flex items-start gap-[11px] py-[7px]">
            <span class="flex size-[22px] shrink-0 items-center justify-center rounded-full bg-amber-400/20 text-[11.5px] font-bold text-amber-400" aria-hidden="true">
                {{ $index + 1 }}
            </span>
            <span class="min-w-0 text-[13.5px] leading-snug text-purple-200">{{ $step }}</span>
        </div>
    @endforeach
</section>
