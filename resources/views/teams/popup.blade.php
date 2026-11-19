<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }}</title>
    <link rel="icon" href="/favicon.ico" sizes="any">

    {{-- Shown for an instant in the Microsoft sign-in popup before it hands
         the result back to the Teams tab and closes itself. --}}
    <style>
        :root { color-scheme: light dark; }
        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            text-align: center;
            font-family: "Segoe UI", system-ui, -apple-system, sans-serif;
            background: #fff;
            color: #242424;
        }
        @media (prefers-color-scheme: dark) {
            body { background: #1f1f1f; color: #f5f5f5; }
        }
        p { font-size: .95rem; line-height: 1.5; margin: 0; max-width: 22rem; }
    </style>

    @vite('resources/js/teams-popup.ts')
</head>
<body>
    <p data-teams-popup
       @if (isset($code)) data-code="{{ $code }}" @endif
       @if (isset($error)) data-error="{{ $error }}" @endif
    >{{ $error ?? 'Finishing sign-in…' }}</p>
</body>
</html>
