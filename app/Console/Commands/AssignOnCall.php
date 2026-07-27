<?php

namespace App\Console\Commands;

use App\Models\OnCallAssignment;
use App\Services\OnCallService;
use App\Services\TeamsNotifier;
use Carbon\CarbonInterface;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Console\Command;

/**
 * Record the on-call ("late dev") developer for the current week. Runs at the
 * start of each week; a manual HR override for the week is left untouched.
 * When a fresh assignment is made, the developer is notified in-app and in Teams.
 */
class AssignOnCall extends Command
{
    protected $signature = 'on-call:assign';

    protected $description = 'Assign the on-call developer for the current week';

    public function handle(OnCallService $onCall): int
    {
        $weekStart = $onCall->weekStart(today());
        $alreadyAssigned = OnCallAssignment::query()->whereDate('week_start', $weekStart)->exists();

        $assignment = $onCall->assignmentForWeek(today(), persist: true);

        if ($assignment?->user === null) {
            $this->warn('No on-call assigned: roster is empty or everyone is out this week.');

            return self::SUCCESS;
        }

        // Only announce a freshly created assignment (not a pre-existing override).
        if (! $alreadyAssigned) {
            $this->notify($assignment);
        }

        $this->info("On-call for week of {$weekStart->toDateString()}: {$assignment->user->name}.");

        return self::SUCCESS;
    }

    /**
     * Tell the on-call developer, in-app and via Teams.
     */
    private function notify(OnCallAssignment $assignment): void
    {
        $weekStart = $assignment->week_start;
        $range = $weekStart->format('M j').' – '.$weekStart->endOfWeek(CarbonInterface::SUNDAY)->format('M j');

        Notification::make()
            ->title("You're on-call this week")
            ->icon(Heroicon::OutlinedPhoneArrowUpRight)
            ->iconColor('warning')
            ->body("You are the on-call developer for the week of {$range}. You're the go-to for urgent issues.")
            ->sendToDatabase(collect([$assignment->user]), isEventDispatched: true);

        app(TeamsNotifier::class)->onCallAssigned($assignment->user, $weekStart);
    }
}
