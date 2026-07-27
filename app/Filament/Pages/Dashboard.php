<?php

namespace App\Filament\Pages;

use App\Models\User;
use App\Services\CelebrationService;
use App\Services\OnCallService;
use Carbon\CarbonInterface;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Support\Icons\Heroicon;

/**
 * The employee home: greeting header, personal stat cards, quick actions,
 * clock in/out, own requests, team status, praise received, and what's
 * coming up. Org-wide HR stats live on the separate HR Overview page.
 */
class Dashboard extends BaseDashboard
{
    protected string $view = 'filament.pages.dashboard';

    public function mount(): void
    {
        $this->alertUnreadNotifications();
    }

    /**
     * Show a red, auto-dismissing toast when the employee lands on the
     * dashboard with unread in-app notifications. Only re-alerts when the
     * unread count has grown since the last visit, so it doesn't nag.
     */
    protected function alertUnreadNotifications(): void
    {
        $user = auth()->user();

        if ($user === null) {
            return;
        }

        $unread = $user->unreadNotifications()->count();
        $lastAlerted = (int) session('unread_notifications_alerted', 0);
        session()->put('unread_notifications_alerted', $unread);

        if ($unread <= 0 || $unread <= $lastAlerted) {
            return;
        }

        Notification::make()
            ->danger()
            ->icon(Heroicon::OutlinedBell)
            ->title($unread === 1
                ? 'You have 1 new notification'
                : "You have {$unread} new notifications")
            ->body('Open the bell menu (top-right) to see the latest updates.')
            ->duration(9000)
            ->send();
    }

    /**
     * Browser tab / document title — includes the signed-in employee's name.
     */
    public function getTitle(): string
    {
        $name = auth()->user()?->name;

        return $name ? "{$name} · Dashboard" : 'Dashboard';
    }

    /**
     * Keep the visible page heading as a plain "Dashboard" (the personalized
     * greeting already lives in the page header).
     */
    public function getHeading(): string
    {
        return 'Dashboard';
    }

    /**
     * Time-of-day greeting for the header.
     */
    public function greeting(): string
    {
        $name = auth()->user()?->first_name ?: auth()->user()?->name;

        $salutation = match (true) {
            now()->hour < 12 => 'Good morning',
            now()->hour < 18 => 'Good afternoon',
            default => 'Good evening',
        };

        return "{$salutation}, {$name}";
    }

    public function departmentName(): ?string
    {
        return auth()->user()?->department?->name;
    }

    /**
     * On-call notice for the signed-in employee, based on today's effective
     * on-call. Returns the week's owner notice, a stand-in notice when they're
     * covering today, or null when it isn't them.
     *
     * @return array{type: 'owner'|'substitute', range: string, covering_for: ?string}|null
     */
    public function myOnCallNotice(): ?array
    {
        $user = auth()->user();

        if ($user === null) {
            return null;
        }

        $service = app(OnCallService::class);
        $effective = $service->onCallForDate(today());

        if ($effective === null || ! $effective['user']->is($user)) {
            return null;
        }

        $weekStart = $service->weekStart(today());

        return [
            'type' => $effective['is_substitute'] ? 'substitute' : 'owner',
            'range' => $weekStart->format('M j').' – '.$weekStart->endOfWeek(CarbonInterface::SUNDAY)->format('M j'),
            'covering_for' => $effective['is_substitute'] ? $effective['primary']?->name : null,
        ];
    }

    /**
     * Today's celebration for the signed-in employee (birthday or work
     * anniversary), shown as a festive greeting on their own dashboard.
     *
     * @return array{type: string, emoji: string, message: string}|null
     */
    public function celebration(): ?array
    {
        $user = auth()->user();

        return $user ? app(CelebrationService::class)->celebrationFor($user) : null;
    }

    /**
     * Names of the user's department leaders (excluding themselves).
     *
     * @return list<string>
     */
    public function leaderNames(): array
    {
        /** @var ?User $user */
        $user = auth()->user();

        if ($user?->department === null) {
            return [];
        }

        return $user->department->leaders()
            ->whereKeyNot($user->id)
            ->orderBy('name')
            ->pluck('name')
            ->all();
    }

    public function todayLabel(): string
    {
        return now()->format('D, j M');
    }

    /**
     * Quick-action links shown under the stat cards.
     *
     * @return list<array{label: string, emoji: string, url: string}>
     */
    public function quickActions(): array
    {
        return array_values(array_filter([
            ['label' => 'Request leave', 'emoji' => '🗓️', 'url' => FileLeaveRequest::getUrl()],
            ['label' => 'Log overtime', 'emoji' => '⏱️', 'url' => FileOverTimeRequest::getUrl()],
            ['label' => 'Give praise', 'emoji' => '✨', 'url' => PraiseWall::getUrl()],
            ['label' => 'DTR', 'emoji' => '📝', 'url' => DailyTimeRecord::getUrl()],
            // filled($profileUrl = Filament::getProfileUrl())
            //     ? ['label' => 'My profile', 'emoji' => '👤', 'url' => $profileUrl]
            //     : null,
        ]));
    }
}
