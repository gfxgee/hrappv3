{{--
    The employee home, drawn for the Microsoft Teams tab.

    Same page object and the same widgets as /admin — this is a skin and a
    running order, not a second dashboard with its own idea of the numbers.
    Two deliberate differences:

    - The on-call banner is gone. The sidebar card states it once, and says
      "it's you" when it is, so the page no longer tells the same person the
      same thing twice.
    - The greeting sits in a purple band rather than on the page background,
      which is what stops the tab reading as an admin panel in an iframe.
--}}
<x-filament-panels::page>
    {{-- The band: greeting, where you sit, and the date --}}
    <div class="hr-bleed rounded-b-[32px] bg-purple-950 px-6 pt-6 pb-7 text-white sm:px-8">
        <div class="mx-auto flex max-w-[1180px] flex-wrap items-end justify-between gap-3">
            <div class="min-w-0">
                <h2 class="text-2xl font-bold tracking-tight">{{ $this->greeting() }}</h2>

                @if ($this->departmentName() || $this->leaderNames() !== [])
                    <p class="mt-1 text-sm text-purple-200">
                        {{ $this->departmentName() }}
                        @if ($this->leaderNames() !== [])
                            <span class="text-purple-300">·</span> reports to {{ implode(', ', $this->leaderNames()) }}
                        @endif
                    </p>
                @endif
            </div>

            <p class="text-sm font-medium text-purple-200">{{ $this->todayLabel() }}</p>
        </div>
    </div>

    <div class="mx-auto w-full max-w-[1180px] space-y-4">
        {{-- Announcements, under the greeting rather than above it, and the
             same width as the cards below. --}}
        @include('filament.announcements.teams-banners', ['inline' => true])

        {{-- Celebration greeting (the signed-in employee's own birthday / anniversary) --}}
        @php($celebration = $this->celebration())
        @if ($celebration)
            <div class="flex items-center gap-3 rounded-[18px] bg-yellow-50 px-[18px] py-3.5 shadow-[inset_0_0_0_1px_rgb(249_159_41/0.4)] dark:bg-yellow-400/10">
                <span class="text-xl" aria-hidden="true">{{ $celebration['emoji'] }}</span>
                <p class="text-sm font-bold text-yellow-900 dark:text-yellow-100">{{ $celebration['message'] }}</p>
            </div>
        @endif

        {{-- Two-column widget grid --}}
        <div class="grid grid-cols-1 gap-4 lg:grid-cols-3 lg:items-start">
            {{-- Left column (2/3) --}}
            <div class="space-y-4 lg:col-span-2">
                @livewire(\App\Filament\Widgets\ClockInOutWidget::class)
                @livewire(\App\Filament\Widgets\Teams\LeaveBalanceStatsWidget::class)

                {{-- Quick actions --}}
                <section aria-labelledby="hr-quick-links" class="rounded-3xl bg-white px-5 pt-[18px] pb-5 shadow-[0_12px_28px_-18px_rgb(31_19_66/0.3)] dark:bg-white/5">
                    <h2 id="hr-quick-links" class="mb-[13px] text-base font-bold tracking-[-0.2px] text-purple-950 dark:text-white">
                        Quick links
                    </h2>

                    <div class="grid grid-cols-2 gap-[11px] sm:grid-cols-4">
                        @foreach ($this->quickActions() as $action)
                            <a
                                href="{{ $action['url'] }}"
                                class="hr-tile flex items-center gap-[11px] rounded-2xl bg-beige-50 px-3.5 py-[13px] text-left dark:bg-white/5"
                            >
                                <span class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-purple-50 text-[17px] dark:bg-white/10" aria-hidden="true">
                                    {{ $action['emoji'] }}
                                </span>
                                <span class="text-[13.5px] leading-tight font-bold text-purple-950 dark:text-white">
                                    {{ $action['label'] }}
                                </span>
                            </a>
                        @endforeach
                    </div>
                </section>

                @livewire(\App\Filament\Widgets\Employee\MyRequestsWidget::class)
                @livewire(\App\Filament\Widgets\OnLeaveTodayWidget::class)
            </div>

            {{-- Right column (1/3) --}}
            <div class="space-y-4">
                @livewire(\App\Filament\Widgets\Teams\OnCallWidget::class)
                @livewire(\App\Filament\Widgets\Employee\ComingUpWidget::class)
                @livewire(\App\Filament\Widgets\Employee\MyPraiseWidget::class)
                @livewire(\App\Filament\Widgets\Employee\MyTeamTodayWidget::class)
                @livewire(\App\Filament\Widgets\Employee\TeamActivityWidget::class)
                @livewire(\App\Filament\Widgets\Employee\MyEquipmentWidget::class)
            </div>
        </div>
    </div>

    {{-- Mood check-in: floating bubble + auto-opening modal (renders as a fixed overlay) --}}
    @livewire(\App\Filament\Widgets\MoodCheckInWidget::class)
</x-filament-panels::page>
