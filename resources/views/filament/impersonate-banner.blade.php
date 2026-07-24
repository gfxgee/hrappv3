@php
    $user = auth()->user();
@endphp

@if ($user && method_exists($user, 'isImpersonated') && $user->isImpersonated())
    <div
        role="alert"
        style="display:flex;align-items:center;justify-content:center;gap:.75rem;flex-wrap:wrap;padding:.5rem 1rem;background:#f59e0b;color:#451a03;font-size:.875rem;font-weight:500;"
    >
        <span>
            You are impersonating <strong>{{ $user->name }}</strong> ({{ $user->email }}).
        </span>

        <a
            href="{{ route('impersonate.leave') }}"
            style="display:inline-flex;align-items:center;gap:.25rem;padding:.25rem .625rem;border-radius:.375rem;background:rgba(69,26,3,.12);font-weight:600;text-decoration:underline;color:inherit;"
        >
            Stop impersonating
        </a>
    </div>
@endif
