<?php

namespace App\Filament\Widgets;

use App\Enum\Mood;
use App\Models\MoodCheckIn;
use Filament\Notifications\Notification;
use Filament\Widgets\Widget;

/**
 * The "moodometer". Renders a floating bubble (bottom-right) and a modal mood
 * picker on the dashboard. The modal auto-opens once per session when the
 * employee hasn't checked in today; dismissing it leaves the bubble so they
 * can check in later. Submitting again the same day updates today's entry.
 */
class MoodCheckInWidget extends Widget
{
    protected string $view = 'filament.widgets.mood-check-in-widget';

    /**
     * Defensive — the panel auth middleware already gates the dashboard.
     */
    public static function canView(): bool
    {
        return auth()->check();
    }

    public function logMood(string $mood): void
    {
        $selected = Mood::tryFrom($mood);

        if ($selected === null) {
            return;
        }

        MoodCheckIn::updateOrCreate(
            ['user_id' => auth()->id(), 'logged_on' => today()],
            ['mood' => $selected->value],
        );

        Notification::make()
            ->success()
            ->title('Mood logged')
            ->body("Thanks for checking in! You're feeling {$selected->label()} {$selected->emoji()} today.")
            ->send();

        $this->dispatch('mood-logged');
    }

    /**
     * The mood the employee already logged today, if any.
     */
    public function todaysMood(): ?Mood
    {
        return MoodCheckIn::query()
            ->forToday()
            ->where('user_id', auth()->id())
            ->value('mood');
    }

    /**
     * Mood options for the picker.
     *
     * @return list<array{value: string, label: string, emoji: string}>
     */
    public function moods(): array
    {
        return array_map(fn (Mood $mood): array => [
            'value' => $mood->value,
            'label' => $mood->label(),
            'emoji' => $mood->emoji(),
        ], Mood::cases());
    }
}
