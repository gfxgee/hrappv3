<?php

namespace App\Filament\Resources\Assets\Pages;

use App\Enum\AssetStatus;
use App\Filament\Resources\Assets\AssetResource;
use App\Models\Asset;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListAssets extends ListRecords
{
    protected static string $resource = AssetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    /**
     * Status filter shown as tabs above the table (with live counts).
     *
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        $tabs = ['all' => Tab::make('All')->badge(Asset::query()->count())];

        foreach (AssetStatus::cases() as $status) {
            $tabs[$status->value] = Tab::make($status->label())
                ->badge(Asset::query()->where('status', $status->value)->count())
                ->badgeColor($status->color())
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', $status->value));
        }

        return $tabs;
    }
}
