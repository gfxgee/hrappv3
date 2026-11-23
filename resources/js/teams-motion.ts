/**
 * The one piece of un-prompted motion in the Teams tab: the figures on the
 * dashboard count up once, on arrival, and the bar beside each one grows to
 * the same eased value so the number and the length it stands for land
 * together.
 *
 * The markup always carries the finished figure. This file only ever takes a
 * value that is already correct and replays it, so with JavaScript blocked,
 * with motion turned down, or mid-animation, what is on screen is the truth.
 *
 * Teams specifics, all of which are the reason this is not two lines of CSS:
 *
 * - The tab is an iframe that Teams keeps alive in the background. Frames are
 *   throttled to a crawl there, so a count left running would stall in mid-air
 *   and land minutes later, while someone is reading a chat, on a figure that
 *   by then looks arbitrary. Leaving the tab finishes it instead.
 * - Teams mounts a tab before showing it, so a count that starts on load can
 *   play to nobody. A hidden document gets the finished figures at once.
 * - The panel navigates with wire:navigate, which swaps the body without a new
 *   document, so arrival is `livewire:navigated` as well as `DOMContentLoaded`.
 */

const DURATION = 900;
const STAGGER = 110;

type Counter = {
    el: HTMLElement;
    to: number;
    places: number;
};

type Bar = {
    el: HTMLElement;
    to: number;
};

/** Marks a group as played, so a Livewire re-render does not replay it. */
const played = new WeakSet<HTMLElement>();

let frame: number | null = null;

function prefersReducedMotion(): boolean {
    return typeof matchMedia === 'function' && matchMedia('(prefers-reduced-motion: reduce)').matches;
}

/** Decelerates into the final figure rather than stopping dead on it. */
function ease(progress: number): number {
    return 1 - (1 - progress) ** 3;
}

function format(value: number, places: number): string {
    return places > 0 ? value.toFixed(places) : String(Math.round(value));
}

function reveal(group: HTMLElement): void {
    if (played.has(group)) {
        return;
    }

    played.add(group);

    const counters: Counter[] = Array.from(group.querySelectorAll<HTMLElement>('[data-count-to]')).map((el) => ({
        el,
        to: Number.parseFloat(el.dataset.countTo ?? '0'),
        places: Number.parseInt(el.dataset.countPlaces ?? '0', 10),
    }));

    const bars: Bar[] = Array.from(group.querySelectorAll<HTMLElement>('[data-grow-to]')).map((el) => ({
        el,
        to: Number.parseFloat(el.dataset.growTo ?? '0'),
    }));

    if (counters.length === 0 && bars.length === 0) {
        return;
    }

    // The markup already holds the finished state, so every reason to skip is
    // served by doing nothing at all.
    if (prefersReducedMotion() || document.hidden) {
        return;
    }

    const started = performance.now();

    const finish = (): void => {
        counters.forEach((counter) => {
            counter.el.textContent = format(counter.to, counter.places);
        });
        bars.forEach((bar) => {
            bar.el.style.width = `${bar.to}%`;
        });
    };

    const step = (now: number): void => {
        const elapsed = now - started;
        let running = false;

        counters.forEach((counter, index) => {
            const progress = Math.min(1, Math.max(0, (elapsed - index * STAGGER) / DURATION));
            counter.el.textContent = format(counter.to * ease(progress), counter.places);
            running = running || progress < 1;
        });

        bars.forEach((bar, index) => {
            const progress = Math.min(1, Math.max(0, (elapsed - index * STAGGER) / DURATION));
            bar.el.style.width = `${bar.to * ease(progress)}%`;
            running = running || progress < 1;
        });

        frame = running ? requestAnimationFrame(step) : null;
    };

    counters.forEach((counter) => {
        counter.el.textContent = format(0, counter.places);
    });
    bars.forEach((bar) => {
        bar.el.style.width = '0%';
    });

    frame = requestAnimationFrame(step);

    const settle = (): void => {
        if (!document.hidden) {
            return;
        }

        if (frame !== null) {
            cancelAnimationFrame(frame);
            frame = null;
        }

        finish();
        document.removeEventListener('visibilitychange', settle);
    };

    document.addEventListener('visibilitychange', settle);
}

function revealAll(): void {
    document.querySelectorAll<HTMLElement>('[data-reveal]').forEach(reveal);
}

document.addEventListener('DOMContentLoaded', revealAll);
document.addEventListener('livewire:navigated', revealAll);
