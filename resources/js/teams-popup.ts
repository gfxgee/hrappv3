import { app, authentication } from '@microsoft/teams-js';

/**
 * Closes the Microsoft sign-in popup, handing its result back to the Teams tab
 * that opened it. The tab turns the code into a session; see teams.ts.
 */
const result = document.querySelector<HTMLElement>('[data-teams-popup]');

if (result) {
    void (async () => {
        try {
            await app.initialize();
        } catch {
            // The SDK is unavailable outside Teams; nothing can be handed back.
            return;
        }

        const code = result.dataset.code;

        if (code) {
            authentication.notifySuccess(code);
        } else {
            authentication.notifyFailure(result.dataset.error ?? 'Sign-in failed.');
        }
    })();
}
