{{--
    Confetti for the praise wall.

    Shared by /admin's wall and the Teams tab's, so the two cannot drift:
    `fallingConfetti` in particular is called by the finish-cycle action
    through `window.fallingConfetti?.()`, which fails silently if the view
    that defines it is not the one on screen.
--}}
    @assets
        <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.9.3/dist/confetti.browser.min.js" defer></script>
    @endassets

    @script
        <script>
            // A celebratory burst from the lower-center — used when a praise is posted.
            window.boomConfetti = function () {
                if (typeof confetti !== 'function') return;

                const count = 200;
                const defaults = { origin: { y: 0.6 }, zIndex: 99999 };
                const fire = (ratio, opts) => confetti({
                    ...defaults,
                    ...opts,
                    particleCount: Math.floor(count * ratio),
                });

                fire(0.25, { spread: 26, startVelocity: 55 });
                fire(0.2, { spread: 60 });
                fire(0.35, { spread: 100, decay: 0.91, scalar: 0.8 });
                fire(0.1, { spread: 120, startVelocity: 25, decay: 0.92, scalar: 1.2 });
                fire(0.1, { spread: 120, startVelocity: 45 });
            };

            // A gentle shower from the top — used when finishing a cycle.
            window.fallingConfetti = function () {
                if (typeof confetti !== 'function') return;

                const end = Date.now() + 2500;
                (function frame() {
                    confetti({
                        particleCount: 4,
                        startVelocity: 0,
                        ticks: 220,
                        gravity: 0.6,
                        spread: 80,
                        zIndex: 99999,
                        origin: { x: Math.random(), y: -0.1 },
                    });

                    if (Date.now() < end) requestAnimationFrame(frame);
                })();
            };

            window.schoolPrideConfetti = function () {
                var colors = ['#e39230', '#fb2c36', '#1e1242', '#1297e9', '#fb2c36', '#fff'];

                if (typeof confetti !== 'function') return;

                const end = Date.now() + 2500;
                (function frame() {
                   confetti({
                        particleCount: 2,
                        angle: 60,
                        spread: 55,
                        origin: { x: 0 },
                        colors: colors
                    });
                    confetti({
                        particleCount: 2,
                        angle: 120,
                        spread: 55,
                        origin: { x: 1 },
                        colors: colors
                    });

                    if (Date.now() < end) requestAnimationFrame(frame);
                })();
            };

            $wire.on('praise-confetti', () => window.boomConfetti());
            $wire.on('praise-opened', () => window.schoolPrideConfetti());
        </script>
    @endscript
