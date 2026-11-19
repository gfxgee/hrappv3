<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name') }}</title>
    <link rel="icon" href="/favicon.ico" sizes="any">

    {{-- A splash screen shown for a second or two while Teams hands over the
         signed-in identity, so it carries its own small styles rather than
         pulling in the whole application bundle. --}}
    <style>
        :root { color-scheme: light dark; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            font-family: "Segoe UI", system-ui, -apple-system, sans-serif;
            background: #fff;
            color: #242424;
        }
        @media (prefers-color-scheme: dark) {
            body { background: #1f1f1f; color: #f5f5f5; }
        }
        .panel { text-align: center; max-width: 22rem; }
        .spinner {
            width: 32px;
            height: 32px;
            margin: 0 auto 20px;
            border: 3px solid currentColor;
            border-top-color: transparent;
            border-radius: 50%;
            opacity: .45;
            animation: spin .8s linear infinite;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
        @media (prefers-reduced-motion: reduce) {
            .spinner { animation-duration: 2.4s; }
        }
        .message { font-size: .95rem; line-height: 1.5; margin: 0; }
        .detail { font-size: .85rem; opacity: .7; margin: .6rem 0 0; }
        .button {
            display: none;
            margin-top: 1.25rem;
            padding: .6rem 1.1rem;
            font: inherit;
            font-weight: 600;
            color: #fff;
            background: #5b5fc7;
            border: 0;
            border-radius: 6px;
            cursor: pointer;
        }
        .button:hover { background: #4f52b2; }
        [hidden] { display: none !important; }
    </style>

    @vite('resources/js/teams.ts')
</head>
<body>
    <main class="panel"
          data-teams-app
          data-nonce="{{ $nonce }}"
          data-destination="{{ $destination }}"
          data-already-signed-in="{{ $alreadySignedIn ? '1' : '0' }}"
          data-token-url="{{ route('teams.auth.token') }}"
          data-claim-url="{{ route('teams.auth.claim') }}"
          data-popup-url="{{ route('sso.microsoft.redirect') }}">
        <div class="spinner" data-spinner></div>
        <p class="message" data-message>Signing you in&hellip;</p>
        <p class="detail" data-detail hidden></p>
        <button type="button" class="button" data-retry>Sign in with Microsoft</button>
    </main>
</body>
</html>
