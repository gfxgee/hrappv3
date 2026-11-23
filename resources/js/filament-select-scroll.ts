/**
 * Open a select where the answer already is.
 *
 * Filament's non-native select always opens its list at the top. On a short
 * list nobody notices. On the quarter-hour time pickers it means a field
 * holding 18:00 opens showing 00:00, and the person scrolls past seventy-two
 * options to reach the one they are already on.
 *
 * So: when a select opens, bring its selected option into view, centred, so
 * the times either side are visible too. Only on open — once the list is
 * being filtered by typing, the scroll position is the search's business.
 *
 * Applies to every select in both panels, because the annoyance is the same
 * wherever the list is long.
 */

const OPEN_KEYS = new Set(['Enter', ' ', 'Spacebar', 'ArrowDown', 'ArrowUp']);

/** Long enough for Alpine to paint the panel, short enough to give up on. */
const MAX_FRAMES = 30;

function centreSelectedOption(container: HTMLElement): void {
    let frames = 0;

    const step = (): void => {
        const panel = container.querySelector<HTMLElement>('.fi-dropdown-panel');
        const option = panel?.querySelector<HTMLElement>('.fi-select-input-option.fi-selected');

        // The panel stays in the DOM when closed, so height is what says it
        // is open and laid out.
        if (panel && option && panel.clientHeight > 0 && option.offsetHeight > 0) {
            const offset = option.getBoundingClientRect().top - panel.getBoundingClientRect().top;
            const centred = offset - (panel.clientHeight - option.offsetHeight) / 2;

            // Nothing to do when it already fits without scrolling.
            if (panel.scrollHeight > panel.clientHeight) {
                panel.scrollTop += centred;
            }

            return;
        }

        if (++frames < MAX_FRAMES) {
            requestAnimationFrame(step);
        }
    };

    requestAnimationFrame(step);
}

function handle(target: EventTarget | null): void {
    if (!(target instanceof Element)) {
        return;
    }

    const button = target.closest<HTMLElement>('.fi-select-input-btn');
    const container = button?.closest<HTMLElement>('.fi-select-input-ctn');

    if (container) {
        centreSelectedOption(container);
    }
}

document.addEventListener('click', (event: MouseEvent): void => handle(event.target));

document.addEventListener('keydown', (event: KeyboardEvent): void => {
    if (OPEN_KEYS.has(event.key)) {
        handle(event.target);
    }
});
