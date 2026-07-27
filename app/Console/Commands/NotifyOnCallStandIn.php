<?php

namespace App\Console\Commands;

use App\Services\OnCallService;
use App\Services\TeamsNotifier;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Console\Command;

/**
 * Each working morning, if the week's on-call developer is on leave today, tell
 * the stand-in who is covering (in-app + Teams). No-op when the owner is in or
 * it isn't a working day.
 */
class NotifyOnCallStandIn extends Command
{
    protected $signature = 'on-call:notify-standin';

    protected $description = 'Notify the stand-in covering on-call today when the owner is on leave';

    public function handle(OnCallService $onCall): int
    {
        if (! $onCall->isWorkingDay(today())) {
            return self::SUCCESS;
        }

        $effective = $onCall->onCallForDate(today());

        // Only notify when someone is actually standing in for the owner.
        if ($effective === null || ! $effective['is_substitute']) {
            return self::SUCCESS;
        }

        $standIn = $effective['user'];
        $primary = $effective['primary'];
        $for = $primary !== null ? " for {$primary->name}" : '';

        Notification::make()
            ->title("You're covering on-call today")
            ->icon(Heroicon::OutlinedPhoneArrowUpRight)
            ->iconColor('warning')
            ->body("You're standing in{$for} today — you're the go-to for urgent issues.")
            ->sendToDatabase(collect([$standIn]), isEventDispatched: true);

        app(TeamsNotifier::class)->onCallStandIn($standIn, $primary, today());

        $this->info("Notified stand-in: {$standIn->name}{$for}.");

        return self::SUCCESS;
    }
}
