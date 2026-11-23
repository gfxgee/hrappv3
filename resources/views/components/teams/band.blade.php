{{--
    The purple band that opens every page in the Teams tab.

    It runs edge to edge (see `.hr-bleed` in the tab's theme) and carries a
    deep bottom padding, because the page's first card is pulled up over it —
    that overlap is what ties the band to the content instead of leaving a
    coloured strip sitting on top of the page.

    Anything passed as the default slot sits on the right of the title: the
    one or two actions that belong to the page as a whole.
--}}
@props([
    'title',
    'subtitle' => null,
])

<div class="hr-bleed rounded-b-[32px] bg-purple-950 px-6 pt-6 pb-16 text-white sm:px-8">
    <div class="mx-auto flex w-full max-w-[1180px] flex-wrap items-end justify-between gap-4">
        <div class="min-w-0">
            <h1 class="text-[26px] leading-tight font-bold tracking-[-0.6px]">{{ $title }}</h1>

            @if (filled($subtitle))
                <p class="mt-1 text-sm text-purple-200">{{ $subtitle }}</p>
            @endif
        </div>

        {{ $slot }}
    </div>
</div>
