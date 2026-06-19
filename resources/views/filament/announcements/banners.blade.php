@php
    use App\Models\Announcement;

    $announcements = auth()->check()
        ? Announcement::liveForUser(auth()->user())
        : collect();

    $urgent = $announcements->where('is_urgent', true);
    $normal = $announcements->where('is_urgent', false);

    // Tailwind classes per announcement type (light + dark).
    $styles = [
        'info' => 'bg-blue-100 text-blue-800 ring-blue-600/20 dark:bg-blue-500/10 dark:text-blue-200 dark:ring-blue-400/30',
        'warning' => 'bg-warning-50 text-warning-800 ring-warning-600/20 dark:bg-warning-500/10 dark:text-warning-200 dark:ring-warning-400/30',
        'success' => 'bg-success-50 text-success-800 ring-success-600/20 dark:bg-success-500/10 dark:text-success-200 dark:ring-success-400/30',
        'danger' => 'bg-red-100 text-danger-800 ring-red-600/20 dark:bg-red-500/10 dark:text-red-200 dark:ring-red-400/30',
    ];
@endphp

@if ($urgent->isNotEmpty())
    {{-- Urgent alerts: prominent, sticky, and not dismissible — they stay until HR deactivates or they expire. --}}
    <div class="sticky top-0 z-20 mt-10 space-y-2">
        @foreach ($urgent as $announcement)
            <div
                class="flex items-start gap-3 rounded-xl border-2 border-red-500 bg-red-50 px-4 py-3 text-sm text-red-900 shadow-md dark:border-red-500/70 dark:bg-red-500/15 dark:text-red-100"
                role="alert"
            >
                <x-filament::icon
                    icon="heroicon-o-exclamation-triangle"
                    class="mt-0.5 h-6 w-6 shrink-0 animate-pulse text-red-600 dark:text-red-300"
                />

                <div class="min-w-0 flex-1 [&_a]:font-medium [&_a]:underline [&_ol]:list-decimal [&_ol]:pl-5 [&_ul]:list-disc [&_ul]:pl-5">
                    <p class="text-xs font-bold uppercase tracking-wide text-red-600 dark:text-red-300">Urgent alert</p>
                    @if (filled($announcement->title))
                        <p class="font-semibold">{{ $announcement->title }}</p>
                    @endif
                    <div class="[&>p]:my-0">{!! $announcement->message !!}</div>
                </div>
            </div>
        @endforeach
    </div>
@endif

@if ($normal->isNotEmpty())
    <div class="mb-4 space-y-2 mt-10">
        @foreach ($normal as $announcement)
            @php($key = 'announcement-'.$announcement->id.'-'.optional($announcement->updated_at)->timestamp)
            @php($classes = $styles[$announcement->type->value] ?? $styles['info'])

            <div
                x-data="{ show: sessionStorage.getItem(@js($key)) !== '1' }"
                x-show="show"
                x-cloak
                class="flex items-start gap-3 rounded-xl px-4 py-3 text-sm shadow-sm ring-1 {{ $classes }}"
            >
                <x-filament::icon :icon="$announcement->type->icon()" class="mt-0.5 h-5 w-5 shrink-0" />

                <div class="min-w-0 flex-1 [&_a]:font-medium [&_a]:underline [&_ol]:list-decimal [&_ol]:pl-5 [&_ul]:list-disc [&_ul]:pl-5">
                    @if (filled($announcement->title))
                        <p class="font-semibold">{{ $announcement->title }}</p>
                    @endif
                    <div class="[&>p]:my-0">{!! $announcement->message !!}</div>
                </div>

                <button
                    type="button"
                    x-on:click="show = false; sessionStorage.setItem(@js($key), '1')"
                    class="-mr-1 shrink-0 rounded-lg p-1 opacity-70 transition hover:opacity-100"
                    aria-label="Dismiss"
                >
                    <x-filament::icon icon="heroicon-o-x-mark" class="h-4 w-4" />
                </button>
            </div>
        @endforeach
    </div>
@endif
