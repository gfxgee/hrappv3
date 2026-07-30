<?php

namespace App\Console\Commands;

use App\Services\CelebrationService;
use App\Services\TeamsNotifier;
use Illuminate\Console\Command;

/**
 * Fires the Power Automate celebrations flow each morning, but only when
 * someone actually has a birthday or work anniversary today — so the flow never
 * runs on an empty day.
 */
class TriggerCelebrationsFlow extends Command
{
    protected $signature = 'celebrations:trigger-flow
                            {--force : Send even when there are no celebrations today}';

    protected $description = "Trigger the Teams celebrations flow with today's birthdays and anniversaries";

    public function handle(CelebrationService $celebrations, TeamsNotifier $teams): int
    {
        if (blank(config('services.teams.celebrations_flow_url'))) {
            $this->warn('TEAMS_CELEBRATIONS_FLOW_URL is not set; nothing sent.');

            return self::SUCCESS;
        }

        $birthdays = $celebrations->birthdaysToday();
        $anniversaries = $celebrations->anniversariesToday();

        if ($birthdays->isEmpty() && $anniversaries->isEmpty() && ! $this->option('force')) {
            $this->info('No celebrations today; flow not triggered.');

            return self::SUCCESS;
        }

        $teams->celebrationsToday($birthdays, $anniversaries);

        $this->info(sprintf(
            'Celebrations flow triggered: %d birthday(s), %d anniversary(ies).',
            $birthdays->count(),
            $anniversaries->count(),
        ));

        return self::SUCCESS;
    }
}
