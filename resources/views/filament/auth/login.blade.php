@php
    $images = $this->carouselImages();
@endphp

<div class="flex min-h-screen w-full bg-white dark:bg-gray-1000">
    {{-- Left: login form --}}
    <div class="flex w-full flex-col justify-center px-6 py-12 sm:px-10 lg:w-1/3 lg:px-16 xl:px-24">
        <div class="mx-auto w-full max-w-md">
            <h1 class="font-serif text-3xl font-bold tracking-tight text-gray-1000 dark:text-white">
                {{ $this->brandName() }}
            </h1>
            <p class="mt-2 text-sm text-gray-700 dark:text-gray-400">
                Welcome back, please log in to your account.
            </p>

            <div class="mt-8">
                {{ $this->content }}
            </div>

            @if (filled(config('services.microsoft.client_id')))
                <div class="mt-6">
                    <div class="relative flex items-center">
                        <div class="flex-1 border-t border-gray-200 dark:border-white/10"></div>
                        <span class="px-3 text-xs uppercase tracking-wide text-gray-400">or</span>
                        <div class="flex-1 border-t border-gray-200 dark:border-white/10"></div>
                    </div>

                    <a
                        href="{{ route('sso.microsoft.redirect') }}"
                        class="mt-4 flex w-full items-center justify-center gap-2.5 rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-semibold text-gray-700 transition hover:bg-gray-50 dark:border-white/20 dark:text-gray-200 dark:hover:bg-white/5"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 23 23" class="h-4 w-4">
                            <path fill="#F25022" d="M1 1h10v10H1z" />
                            <path fill="#7FBA00" d="M12 1h10v10H12z" />
                            <path fill="#00A4EF" d="M1 12h10v10H1z" />
                            <path fill="#FFB900" d="M12 12h10v10H12z" />
                        </svg>
                        Sign in with Microsoft
                    </a>
                </div>
            @endif
        </div>
    </div>

    {{-- Right: brand carousel (hidden on small screens) --}}
    <div
        class="relative hidden overflow-hidden lg:block lg:w-2/3 lg:[clip-path:polygon(10%_0,100%_0,100%_100%,0_100%)]"
    >
        @if (count($images))
            <div
                x-data="{ active: 0, count: {{ count($images) }} }"
                x-init="if (count > 1) setInterval(() => active = (active + 1) % count, 5000)"
                class="relative h-full w-full bg-gray-1000"
            >
                @foreach ($images as $index => $src)
                    <img
                        src="{{ $src }}"
                        alt="{{ $this->brandName() }}"
                        @if ($index > 0) x-show="active === {{ $index }}" x-cloak @endif
                        x-transition:enter="transition ease-out duration-700"
                        x-transition:enter-start="opacity-0"
                        x-transition:enter-end="opacity-100"
                        class="absolute inset-0 h-full w-full object-cover"
                    />
                @endforeach

                @if (count($images) > 1)
                    <div class="absolute bottom-6 left-1/2 flex -translate-x-1/2 gap-2">
                        @foreach ($images as $index => $src)
                            <button
                                type="button"
                                x-on:click="active = {{ $index }}"
                                class="h-2.5 w-2.5 rounded-full transition"
                                x-bind:class="active === {{ $index }} ? 'bg-white' : 'bg-white/40 hover:bg-white/70'"
                                aria-label="Go to slide {{ $index + 1 }}"
                            ></button>
                        @endforeach
                    </div>
                @endif
            </div>
        @else
            {{-- Branded fallback when no images are present --}}
            <div class="flex h-full w-full flex-col items-center justify-center bg-gradient-to-br from-purple-700 to-purple-1000 text-center text-white">
                <span class="text-6xl">🌱</span>
                <p class="mt-5 text-3xl font-bold">{{ $this->brandName() }}</p>
                <p class="mt-2 max-w-xs text-sm text-white/70">
                    Small steps towards a greener future.
                </p>
                <p class="mt-6 text-xs text-white/50">
                    Add images to <code>public/images/login/</code> to show them here.
                </p>
            </div>
        @endif
    </div>
</div>
