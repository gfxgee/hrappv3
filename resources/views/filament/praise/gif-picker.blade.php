<div x-data="{ open: false }" class="space-y-2">
    {{-- Chosen GIF preview (stays visible after the popover closes) --}}
    @if ($this->selectedGifUrl)
        <div class="relative inline-block">
            <img
                src="{{ $this->selectedGifUrl }}"
                alt="Selected GIF"
                class="max-h-40 rounded-lg border border-gray-200 object-contain dark:border-white/10"
            />
            <button
                type="button"
                wire:click="clearSelectedGif"
                title="Remove GIF"
                class="absolute -right-2 -top-2 flex h-6 w-6 items-center justify-center rounded-full bg-gray-900/80 text-white shadow transition hover:bg-gray-900"
            >
                <span class="text-xs leading-none">&times;</span>
            </button>
        </div>
    @endif

    {{-- Trigger + floating picker --}}
    <div class="relative inline-block">
        <button
            type="button"
            x-on:click="open = ! open; if (open) $nextTick(() => $refs.gifSearch?.focus())"
            @class([
                'inline-flex items-center gap-1.5 rounded-lg border px-2.5 py-1.5 text-sm font-semibold transition',
                'border-primary-500 text-primary-600 dark:text-primary-400' => false,
                'border-gray-300 text-gray-600 hover:bg-gray-50 dark:border-white/20 dark:text-gray-300 dark:hover:bg-white/5',
            ])
        >
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-4 w-4">
                <path fill-rule="evenodd" d="M3.75 4.5A2.25 2.25 0 0 0 1.5 6.75v10.5A2.25 2.25 0 0 0 3.75 19.5h16.5a2.25 2.25 0 0 0 2.25-2.25V6.75a2.25 2.25 0 0 0-2.25-2.25H3.75ZM7.5 9.75a2.25 2.25 0 1 0 0 4.5c.97 0 1.5-.5 1.5-.5v-1.5H7.875a.375.375 0 0 1 0-.75H10.5v2.69s-.9 1.06-3 1.06a3.75 3.75 0 1 1 2.33-6.69.375.375 0 1 1-.466.588A3 3 0 0 0 7.5 9.75Zm5.25-.375a.375.375 0 0 0-.75 0v5.25a.375.375 0 0 0 .75 0V9.375Zm2.25-.375a.375.375 0 0 0-.375.375v5.25a.375.375 0 0 0 .75 0v-2.25h1.875a.375.375 0 0 0 0-.75H15.375v-1.5h2.25a.375.375 0 0 0 0-.75H15Z" clip-rule="evenodd" />
            </svg>
            GIF
        </button>

        <div
            x-show="open"
            x-cloak
            x-transition.origin.bottom.left
            x-on:click.outside="open = false"
            x-on:keydown.escape.window="open = false"
            class="absolute bottom-full left-0 z-50 mb-2 w-80 rounded-xl border border-gray-200 bg-white p-3 shadow-2xl dark:border-white/10 dark:bg-gray-900"
        >
            <input
                type="search"
                x-ref="gifSearch"
                wire:model.live.debounce.500ms="gifQuery"
                placeholder="Search GIFs…"
                autocomplete="off"
                class="block w-full px-2 py-3 rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-white/20 dark:bg-white/5 dark:text-white"
            />

            <div wire:loading.flex wire:target="gifQuery,searchGifs" class="mt-3 items-center justify-center gap-2 text-xs text-gray-400">
                <x-filament::loading-indicator class="h-4 w-4" />
                <span>Searching GIFs…</span>
            </div>

            @if (filled($this->gifQuery) && count($this->gifResults) === 0)
                <p wire:loading.remove wire:target="gifQuery,searchGifs" class="mt-3 text-center text-xs text-gray-400">
                    No GIFs found — try another search.
                </p>
            @elseif (blank($this->gifQuery))
                <p class="mt-3 text-center text-xs text-gray-400">
                    Type to search for the perfect GIF.
                </p>
            @endif

            @if (count($this->gifResults) > 0)
                <div class="mt-2 grid max-h-64 grid-cols-2 gap-2 overflow-y-auto">
                    @foreach ($this->gifResults as $gif)
                        <button
                            type="button"
                            wire:key="gif-{{ $gif['id'] }}"
                            wire:click="selectGif(@js($gif['full']))"
                            x-on:click="open = false"
                            class="overflow-hidden rounded-lg ring-1 ring-gray-200 transition hover:ring-2 hover:ring-primary-500 dark:ring-white/10"
                        >
                            <img src="{{ $gif['preview'] }}" alt="GIF" loading="lazy" class="h-24 w-full object-cover" />
                        </button>
                    @endforeach

                    {{-- Sentinel: when it scrolls into view it loads the next page. --}}
                    @if ($this->gifHasMore)
                        <div
                            wire:key="gif-load-more"
                            x-intersect="$wire.loadMoreGifs()"
                            class="col-span-2 flex items-center justify-center gap-2 py-3 text-xs text-gray-400"
                        >
                            <x-filament::loading-indicator class="h-4 w-4" />
                            <span>Loading more GIFs…</span>
                        </div>
                    @endif
                </div>

                <p class="mt-2 text-right text-[10px] text-gray-400">
                    Powered by {{ ucfirst(config('services.gif.provider', 'giphy')) }}
                </p>
            @endif
        </div>
    </div>
</div>
