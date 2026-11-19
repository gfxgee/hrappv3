import { app, authentication } from '@microsoft/teams-js';

/**
 * Signs the employee in to the Microsoft Teams tab.
 *
 * Teams can hand the page a token for whoever is already signed in to Teams,
 * so nobody types anything. That silent path needs the tenant to have
 * consented to the app once; until then — or on a host client that cannot do
 * it — we fall back to the ordinary Microsoft popup.
 *
 * The popup cannot establish the session itself: it is a top-level window, so
 * its cookies land in a different jar from the partitioned one this embedded
 * tab uses. It returns a single-use code instead, which this page redeems so
 * the session cookie is written in the tab's own context.
 */

const root = document.querySelector<HTMLElement>('[data-teams-app]');

if (root) {
    void start(root);
}

async function start(root: HTMLElement): Promise<void> {
    const destination = root.dataset.destination ?? '/';

    try {
        await app.initialize();
        app.notifySuccess();
    } catch {
        // Opened outside Teams — there is nothing to hand over here.
        offerManualSignIn(root, 'Open this page from the Microsoft Teams app to sign in automatically.');

        return;
    }

    if (root.dataset.alreadySignedIn === '1') {
        window.location.replace(destination);

        return;
    }

    const silent = await signInSilently(root);

    if (silent) {
        window.location.replace(silent);

        return;
    }

    const viaPopup = await signInWithPopup(root);

    if (viaPopup) {
        window.location.replace(viaPopup);
    }
}

/**
 * @returns The destination to navigate to, or null when the silent path is
 *          unavailable and the popup should be tried instead.
 */
async function signInSilently(root: HTMLElement): Promise<string | null> {
    let token: string;

    try {
        token = await authentication.getAuthToken();
    } catch {
        return null;
    }

    const result = await post(root.dataset.tokenUrl, { token });

    if (result.ok) {
        return result.redirect;
    }

    // A refusal (an account that is not an active employee) is final —
    // repeating it through the popup would only ask the same question again.
    if (!result.retryWithPopup) {
        showError(root, result.message);
    }

    return null;
}

async function signInWithPopup(root: HTMLElement): Promise<string | null> {
    setMessage(root, 'Waiting for Microsoft sign-in…');

    const popupUrl = new URL(root.dataset.popupUrl ?? '', window.location.origin);
    popupUrl.searchParams.set('teams_nonce', root.dataset.nonce ?? '');

    let code: string;

    try {
        code = await authentication.authenticate({
            url: popupUrl.toString(),
            width: 600,
            height: 650,
        });
    } catch (reason) {
        // Teams rejects with its own code, or with whatever the popup passed
        // to notifyFailure — which is the only account of what went wrong.
        offerManualSignIn(root, describeAuthFailure(reason));

        return null;
    }

    setMessage(root, 'Signing you in…');

    const result = await post(root.dataset.claimUrl, { code });

    if (result.ok) {
        return result.redirect;
    }

    showError(root, result.message);

    return null;
}

/**
 * Turn a rejection from the Teams auth popup into something actionable.
 * Only a genuine user cancellation should read as one.
 */
function describeAuthFailure(reason: unknown): string {
    const message = reason instanceof Error ? reason.message : String(reason ?? '');

    if (message.includes('CancelledByUser')) {
        return 'Sign-in was cancelled.';
    }

    if (message.includes('FailedToOpenWindow')) {
        return 'Teams could not open the Microsoft sign-in window. If you are on the web app, allow pop-ups for Teams and try again.';
    }

    return message === '' ? 'Sign-in did not complete.' : message;
}

type SignInResult =
    | { ok: true; redirect: string }
    | { ok: false; message: string; retryWithPopup: boolean };

async function post(url: string | undefined, body: Record<string, string>): Promise<SignInResult> {
    const failure = (message: string, retryWithPopup = false): SignInResult => ({
        ok: false,
        message,
        retryWithPopup,
    });

    if (!url) {
        return failure('Sign-in is not configured correctly.');
    }

    let response: Response;

    try {
        response = await fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify(body),
        });
    } catch {
        return failure('We could not reach the server. Check your connection and try again.');
    }

    const payload: unknown = await response.json().catch(() => null);
    const data = (payload ?? {}) as { redirect?: string; message?: string; retry?: string };

    if (response.ok && data.redirect) {
        return { ok: true, redirect: data.redirect };
    }

    return failure(
        data.message ?? 'We could not sign you in. Please try again.',
        data.retry === 'popup',
    );
}

function csrfToken(): string {
    return document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
}

function setMessage(root: HTMLElement, message: string): void {
    const target = root.querySelector<HTMLElement>('[data-message]');

    if (target) {
        target.textContent = message;
    }
}

function showError(root: HTMLElement, message: string): void {
    root.querySelector<HTMLElement>('[data-spinner]')?.remove();
    setMessage(root, 'We could not sign you in');

    const detail = root.querySelector<HTMLElement>('[data-detail]');

    if (detail) {
        detail.textContent = message;
        detail.hidden = false;
    }
}

/**
 * Leaves the employee a way forward when nothing automatic worked.
 */
function offerManualSignIn(root: HTMLElement, reason: string): void {
    showError(root, reason);

    const button = root.querySelector<HTMLButtonElement>('[data-retry]');

    if (!button) {
        return;
    }

    button.style.display = 'inline-block';
    button.addEventListener('click', () => {
        void signInWithPopup(root).then((destination) => {
            if (destination) {
                window.location.replace(destination);
            }
        });
    });
}
