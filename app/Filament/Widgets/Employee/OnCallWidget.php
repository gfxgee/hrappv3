<?php

namespace App\Filament\Widgets\Employee;

use App\Services\OnCallService;
use Carbon\CarbonInterface;
use Filament\Widgets\Widget;

/**
 * A compact card showing which developer is on-call this week, so the whole
 * team knows who to reach for urgent issues. Hidden when no roster exists.
 */
class OnCallWidget extends Widget
{
    protected string $view = 'filament.widgets.employee.on-call-widget';

    protected int|string|array $columnSpan = ['default' => 1, 'md' => 1];

    protected static ?int $sort = 2;

    public static function canView(): bool
    {
        // Only surface the card when someone is effectively on-call today.
        return auth()->check()
            && app(OnCallService::class)->onCallForDate(today()) !== null;
    }

    /**
     * The developer effectively on-call today and the week range, or null when
     * the roster is empty / nobody is available.
     *
     * @return array{name: string, range: string, is_me: bool, covering_for: ?string}|null
     */
    public function onCall(): ?array
    {
        $service = app(OnCallService::class);
        $effective = $service->onCallForDate(today());

        if ($effective === null) {
            return null;
        }

        $weekStart = $service->weekStart(today());

        return [
            'name' => $effective['user']->name,
            'range' => $weekStart->format('M j').' – '.$weekStart->endOfWeek(CarbonInterface::SUNDAY)->format('M j'),
            'is_me' => $effective['user']->is(auth()->user()),
            'covering_for' => $effective['is_substitute'] ? $effective['primary']?->name : null,
        ];
    }
}
