<?php

namespace App\Filament\Widgets\Concerns;

use App\Models\Holiday;
use Filament\Actions\Action;
use Filament\Support\Enums\Alignment;
use Illuminate\Contracts\View\View;

/**
 * Provides the read-only "holiday details" modal action shared by widgets
 * that list holidays. The host widget must implement Filament's HasActions +
 * HasSchemas (InteractsWithActions / InteractsWithSchemas) and mount the
 * action with `mountAction('viewHoliday', { holiday: <id> })`.
 */
trait ShowsHolidayDetails
{
    /**
     * Read-only modal showing a holiday's full details (mounted from a list).
     */
    public function viewHolidayAction(): Action
    {
        return Action::make('viewHoliday')
            ->color('info')
            ->modalHeading(fn (array $arguments): string => $this->holidayModalHeading($this->findHoliday($arguments)))
            ->modalDescription(fn (array $arguments): ?string => $this->holidayModalDescription($this->findHoliday($arguments)))
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
    protected function holidayModalHeading(?Holiday $holiday): string
    {
        if ($holiday === null) {
            return 'Holiday';
        }

        return trim(($holiday->emoji ? $holiday->emoji.' ' : '').$holiday->name);
    }

    /** Subheading text for the holiday modal: date and duration. */
    protected function holidayModalDescription(?Holiday $holiday): ?string
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
