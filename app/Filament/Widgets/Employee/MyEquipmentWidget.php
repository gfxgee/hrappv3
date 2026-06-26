<?php

namespace App\Filament\Widgets\Employee;

use App\Models\Asset;
use Carbon\CarbonInterface;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;

/**
 * The equipment currently assigned to or borrowed by the signed-in employee,
 * mirroring what IT/HR have on record. Read-only — assignment is managed by
 * admins through the Asset Management section.
 */
class MyEquipmentWidget extends Widget
{
    protected string $view = 'filament.widgets.employee.my-equipment-widget';

    protected static ?int $sort = 3;

    public static function canView(): bool
    {
        return auth()->check();
    }

    /**
     * Assets currently held by the signed-in employee.
     *
     * @return Collection<int, array{name: string, category: string, tag: ?string, type: ?string, typeColor: string, since: ?CarbonInterface, due: ?CarbonInterface}>
     */
    public function equipment(): Collection
    {
        return Asset::query()
            ->where('assigned_to', auth()->id())
            ->with('currentAssignment')
            ->orderBy('category')
            ->get()
            ->map(fn (Asset $asset): array => [
                'name' => $asset->name,
                'category' => $asset->category?->label() ?? 'Other',
                'tag' => $asset->asset_tag,
                'type' => $asset->currentAssignment?->type?->label(),
                'typeColor' => $asset->currentAssignment?->type?->color() ?? 'gray',
                'since' => $asset->currentAssignment?->assigned_at,
                'due' => $asset->currentAssignment?->due_at,
            ]);
    }
}
