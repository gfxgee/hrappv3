<?php

namespace App\Filament\Widgets;

use App\Models\Holiday;
use App\Settings\GeneralSettings;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Support\Enums\Alignment;
use Filament\Widgets\Widget;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class UpcomingHolidaysWidget extends Widget implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;

    protected string $view = 'filament.widgets.upcoming-holidays-widget';

    /** One-third width on desktop; sits below Upcoming Birthdays. */
    protected int|string|array $columnSpan = ['default' => 1, 'md' => 1];

    protected static ?int $sort = 0;

    /** Default look-ahead window (days), seeded into settings. */
    public const WINDOW_DAYS = 90;

    public static function canView(): bool
    {
        return auth()->check();
    }

    /** Configured look-ahead window in days. */
    public function windowDays(): int
    {
        return app(GeneralSettings::class)->holidayWindowDays;
    }

    /**
     * Holidays from today through the window, soonest first.
     *
     * @return Collection<int, array{id: int, name: string, emoji: ?string, date: Carbon, day: string, duration: string, days: int, isToday: bool}>
     */
    public function holidays(): Collection
    {
        $today = today();

        return Holiday::query()
            ->active()
            ->whereBetween('date', [
                $today->toDateString(),
                $today->copy()->addDays($this->windowDays())->toDateString(),
            ])
            ->orderBy('date')
            ->take(6)
            ->get()
            ->map(fn (Holiday $holiday): array => [
                'id' => $holiday->id,
                'name' => $holiday->name,
                'emoji' => $holiday->emoji,
                'date' => $holiday->date,
                'day' => $holiday->date->format('l'),
                'duration' => $holiday->duration?->label() ?? 'Full day',
                'days' => (int) $today->diffInDays($holiday->date),
                'isToday' => $holiday->date->isSameDay($today),
            ]);
    }

    /**
     * Read-only modal showing a holiday's full details (mounted from the list).
     */
    public function viewHolidayAction(): Action
    {
        return Action::make('viewHoliday')
            ->color('info')
            ->modalHeading(fn (array $arguments): string => $this->headingFor($this->findHoliday($arguments)))
            ->modalDescription(fn (array $arguments): ?string => $this->descriptionFor($this->findHoliday($arguments)))
            ->modalIcon('heroicon-o-calendar-days')
            ->modalContent(fn (array $arguments): ?View => ($holiday = $this->findHoliday($arguments)) !== null
                ? view('filament.widgets.holiday-details', ['holiday' => $holiday])
                : null)
            ->modalAlignment(Alignment::Start)
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Close')
            ->stickyModalHeader();
    }

    /** Heading text for the holiday modal: emoji + name. */
    protected function headingFor(?Holiday $holiday): string
    {
        if ($holiday === null) {
            return 'Holiday';
        }

        return trim(($holiday->emoji ? $holiday->emoji.' ' : '').$holiday->name);
    }

    /** Subheading text for the holiday modal: date and duration. */
    protected function descriptionFor(?Holiday $holiday): ?string
    {
        if ($holiday === null) {
            return null;
        }

        return $holiday->date->format('l, F j, Y').' · '.($holiday->duration?->label() ?? 'Full day');
    }

    protected function findHoliday(array $arguments): ?Holiday
    {
        return isset($arguments['holiday'])
            ? Holiday::find($arguments['holiday'])
            : null;
    }
}
