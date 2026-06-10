<?php

namespace App\Filament\Widgets\Employee;

use App\Filament\Pages\PraiseWall;
use App\Models\Praise;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;

/**
 * The latest praises the viewer has received, with a link to the wall.
 */
class MyPraiseWidget extends Widget
{
    protected string $view = 'filament.widgets.employee.my-praise-widget';

    protected int|string|array $columnSpan = ['default' => 1, 'md' => 1];

    protected static ?int $sort = 0;

    public static function canView(): bool
    {
        return auth()->check();
    }

    /**
     * @return Collection<int, Praise>
     */
    public function praises(): Collection
    {
        return Praise::query()
            ->with(['sender', 'badge'])
            ->where('recipient_id', auth()->id())
            ->latest()
            ->take(3)
            ->get();
    }

    public function praiseWallUrl(): string
    {
        return PraiseWall::getUrl();
    }
}
