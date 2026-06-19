<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\CelebrationService;
use Filament\Notifications\Notification;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\HtmlString;

#[Signature('app:notify-celebrations')]
#[Description("Send a daily in-app notification announcing today's birthdays and work anniversaries.")]
class NotifyCelebrations extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(CelebrationService $celebrations): int
    {
        $lines = collect();

        foreach ($celebrations->birthdaysToday() as $person) {
            $lines->push("🎂 It's {$person->name}'s birthday today!");
        }

        foreach ($celebrations->anniversariesToday() as $entry) {
            $years = $entry['years'];
            $lines->push(sprintf(
                '🎉 %s is celebrating %d %s with the team today!',
                $entry['user']->name,
                $years,
                $years === 1 ? 'year' : 'years',
            ));
        }

        if ($lines->isEmpty()) {
            $this->info('No celebrations today.');

            return self::SUCCESS;
        }

        $recipients = User::query()->active()->get();

        if ($recipients->isEmpty()) {
            $this->info('No active employees to notify.');

            return self::SUCCESS;
        }

        Notification::make()
            ->title('Celebrations today')
            ->icon('heroicon-o-gift')
            ->iconColor('warning')
            ->body(new HtmlString($lines->implode('<br>')))
            ->sendToDatabase($recipients);

        $this->info(sprintf(
            'Notified %d employee(s) of %d celebration(s).',
            $recipients->count(),
            $lines->count(),
        ));

        return self::SUCCESS;
    }
}
