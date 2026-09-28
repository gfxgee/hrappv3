<?php

namespace App\Filament\Widgets;

use App\Enum\Mood;
use App\Models\MoodCheckIn;
use App\Services\MoodNotifier;
use App\Settings\GeneralSettings;
use Filament\Notifications\Notification;
use Filament\Widgets\Widget;

/**
 * The "moodometer". Renders a floating bubble (bottom-right) and a modal mood
 * picker on the dashboard. The modal auto-opens once per session when the
 * employee hasn't checked in today; dismissing it leaves the bubble so they
 * can check in later. Submitting again the same day updates today's entry.
 *
 * Moods that signal a need for support open a short follow-up: a guided
 * breathing exercise and micro-break for stress, a longer micro-break when
 * tired, and a telehealth link when sick.
 */
class MoodCheckInWidget extends Widget
{
    protected string $view = 'filament.widgets.mood-check-in-widget';

    /**
     * The mood just logged, while its follow-up panel is on screen.
     */
    public ?string $followUp = null;

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

        $previous = $this->todaysMood();

        MoodCheckIn::updateOrCreate(
            ['user_id' => auth()->id(), 'logged_on' => today()],
            ['mood' => $selected->value],
        );

        // Only announce a *change* to sick, so re-opening the picker or
        // re-selecting the same mood doesn't notify the manager twice.
        if ($selected === Mood::SICK && $previous !== Mood::SICK) {
            app(MoodNotifier::class)->checkedInSick(auth()->user());
        }

        Notification::make()
            ->success()
            ->title('Mood logged')
            ->body("Thanks for checking in! You're feeling {$selected->label()} {$selected->emoji()} today.")
            ->send();

        $this->followUp = $selected->needsAttention() ? $selected->value : null;

        $this->dispatch('mood-logged', followUp: $this->followUp !== null);
    }

    /**
     * Close the follow-up panel.
     */
    public function dismissFollowUp(): void
    {
        $this->followUp = null;

        $this->dispatch('mood-follow-up-dismissed');
    }

    /**
     * The follow-up shown after a mood that signals a need for support, or null
     * when there is nothing to offer.
     *
     * @return array{
     *     mood: string,
     *     emoji: string,
     *     title: string,
     *     body: string,
     *     breathing: bool,
     *     break_minutes: ?int,
     *     link_label: ?string,
     *     link_url: ?string,
     *     note: ?string,
     * }|null
     */
    public function followUpPanel(): ?array
    {
        $mood = $this->followUp !== null ? Mood::tryFrom($this->followUp) : null;

        if ($mood === null) {
            return null;
        }

        $settings = app(GeneralSettings::class);

        return match ($mood) {
            Mood::STRESSED => [
                'mood' => $mood->value,
                'emoji' => $mood->emoji(),
                'title' => 'Let’s take a minute',
                'body' => 'Follow the circle for one minute of box breathing, then step away from the screen for 2 minutes.',
                'breathing' => true,
                'break_minutes' => 2,
                'link_label' => 'Talk to HR',
                'link_url' => $settings->hrSupportUrl,
                'note' => null,
            ],
            Mood::TIRED => [
                'mood' => $mood->value,
                'emoji' => $mood->emoji(),
                'title' => 'Time for a micro-break',
                'body' => 'Stand up, stretch, and look away from the screen for 10 minutes. It helps more than pushing through.',
                'breathing' => false,
                'break_minutes' => 10,
                'link_label' => null,
                'link_url' => null,
                'note' => null,
            ],
            Mood::SICK => [
                'mood' => $mood->value,
                'emoji' => $mood->emoji(),
                'title' => 'Hope you feel better soon',
                'body' => 'Rest up. If you need to see a doctor, you can start a virtual consult below.',
                'breathing' => false,
                'break_minutes' => null,
                'link_label' => 'Start a teleconsult',
                'link_url' => $settings->telehealthUrl,
                'note' => 'Your manager and HR have been notified that you’re offline today.',
            ],
            default => null,
        };
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
     * @return list<array{value: string, label: string, emoji: string, lottie: string}>
     */
    public function moods(): array
    {
        return array_map(fn (Mood $mood): array => [
            'value' => $mood->value,
            'label' => $mood->label(),
            'emoji' => $mood->emoji(),
            'lottie' => $mood->lottieCodepoint(),
        ], Mood::cases());
    }
}
