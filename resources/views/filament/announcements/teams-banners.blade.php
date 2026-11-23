{{--
    Announcements, drawn for the Microsoft Teams tab.

    The admin banner prints the whole announcement wherever it appears, which
    is fine on a page people arrive at to read. In the tab it lands above the
    greeting, so a long HR notice pushes the entire dashboard below the fold
    and the thing people opened the tab for is gone.

    So: a notice is one line and a glyph, and the body opens on a click.
    Nothing is dropped — the full text is in the page, one button away, and a
    short announcement still shows in full with no button at all. Urgent
    alerts are the exception and stay open, because that is what urgent means.
--}}
@php
    use App\Models\Announcement;
    use Illuminate\Support\Str;
    use Livewire\Livewire;

    /*
     * Every skinned Teams page draws this itself, under its greeting band,
     * so the band stays the top of the page and the notice lines up with
     * the cards beneath it. Pages with no band — resource tables reached
     * from a widget link, say — still get it from the panel's PAGE_START
     * hook, at the top where there is nothing for it to sit under.
     */
    $inline ??= false;

    // The page that is rendering says whether it has already drawn this.
    // Asking Livewire for the current component works on the first load and
    // on every update after it; matching the route only works on the first.
    // `current()` is `last()` over the component stack, so it is `false`
    // when there is none — rendering this view directly, as the tests do.
    $page = Livewire::current();

    $drawnInsideThePage = is_object($page)
        && method_exists($page, 'drawsOwnAnnouncements')
        && $page->drawsOwnAnnouncements();

    $announcements = ($inline || ! $drawnInsideThePage) && auth()->check()
        ? Announcement::liveForUser(auth()->user())
        : collect();

    /**
     * The rich-text body as one line of readable prose, for the collapsed
     * summary. Block tags become spaces first so words either side of a
     * paragraph break do not run together.
     */
    $plain = function (?string $html): string {
        $spaced = preg_replace('#</(p|div|li|tr|h[1-6])>|<br\s*/?>#i', ' ', (string) $html);

        return trim((string) preg_replace('/\s+/', ' ', html_entity_decode(
            strip_tags((string) $spaced),
            ENT_QUOTES | ENT_HTML5,
        )));
    };

    /** Longer than this and the body is worth collapsing. */
    $summaryLength = 150;

    $glyphs = [
        'info' => 'bg-blue-500/15 text-blue-700 dark:text-blue-300',
        'warning' => 'bg-warning-500/20 text-warning-700 dark:text-warning-300',
        'success' => 'bg-success-500/20 text-success-700 dark:text-success-300',
        'danger' => 'bg-red-500/15 text-red-700 dark:text-red-300',
    ];
@endphp

@if ($announcements->isNotEmpty())
    <div class="space-y-2">
        @foreach ($announcements as $announcement)
            @php
                $body = $plain($announcement->message);
                $isLong = Str::length($body) > $summaryLength;
                $isUrgent = (bool) $announcement->is_urgent;
                $glyph = $glyphs[$announcement->type->value] ?? $glyphs['info'];
                $heading = filled($announcement->title)
                    ? $announcement->title
                    : ($isUrgent ? 'Urgent alert' : $announcement->type->label());
                $key = 'announcement-'.$announcement->id.'-'.optional($announcement->updated_at)->timestamp;
            @endphp

            <div
                @if ($isUrgent)
                    role="alert"
                    x-data="{ open: true }"
                    class="hr-card flex items-start gap-3 px-4 py-3.5 shadow-[inset_0_0_0_2px_rgb(239_68_68/0.7)]"
                @else
                    x-data="{ show: sessionStorage.getItem(@js($key)) !== '1', open: {{ $isLong ? 'false' : 'true' }} }"
                    x-show="show"
                    x-cloak
                    class="hr-card flex items-start gap-3 px-4 py-3.5"
                @endif
            >
                <span
                    class="flex size-8 shrink-0 items-center justify-center rounded-xl {{ $isUrgent ? 'animate-pulse bg-red-500/15 text-red-600 dark:text-red-300' : $glyph }}"
                    aria-hidden="true"
                >
                    <x-filament::icon
                        :icon="$isUrgent ? 'heroicon-o-exclamation-triangle' : $announcement->type->icon()"
                        class="size-[18px]"
                    />
                </span>

                <div class="min-w-0 flex-1">
                    <p class="text-[13.5px] leading-tight font-bold text-purple-950 dark:text-white">
                        {{ $heading }}
                    </p>

                    {{-- Collapsed: one line. Expanded: the announcement as written. --}}
                    @if ($isLong && ! $isUrgent)
                        <p
                            x-show="! open"
                            class="mt-1 truncate text-[13px]"
                            style="color: var(--hr-ink-soft)"
                        >{{ $body }}</p>
                    @endif

                    <div
                        @if ($isLong && ! $isUrgent) x-show="open" x-cloak @endif
                        class="fi-announcement-body mt-1 text-[13px]"
                        style="color: var(--hr-ink-soft)"
                    >{!! $announcement->message !!}</div>

                    @if ($isLong && ! $isUrgent)
                        <button
                            type="button"
                            x-on:click="open = ! open"
                            x-text="open ? 'Show less' : 'Read the full notice'"
                            class="mt-1.5 text-[12.5px] font-bold text-purple-700 hover:underline dark:text-purple-300"
                        ></button>
                    @endif
                </div>

                {{-- Urgent alerts stay until HR retires them, so they carry no
                     dismiss control. --}}
                @unless ($isUrgent)
                    <button
                        type="button"
                        x-on:click="show = false; sessionStorage.setItem(@js($key), '1')"
                        class="-mr-1 shrink-0 rounded-lg p-1 opacity-60 transition hover:opacity-100"
                        aria-label="Dismiss {{ $heading }}"
                    >
                        <x-filament::icon icon="heroicon-o-x-mark" class="size-4" />
                    </button>
                @endunless
            </div>
        @endforeach
    </div>
@endif
