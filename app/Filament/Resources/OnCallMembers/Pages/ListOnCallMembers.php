<?php

namespace App\Filament\Resources\OnCallMembers\Pages;

use App\Filament\Resources\OnCallMembers\OnCallMemberResource;
use App\Services\OnCallService;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListOnCallMembers extends ListRecords
{
    protected static string $resource = OnCallMemberResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Add developer'),
        ];
    }

    /**
     * Surface who is on-call this week right in the page header.
     */
    public function getSubheading(): ?string
    {
        $assignment = app(OnCallService::class)->assignmentForWeek(today());

        if ($assignment?->user === null) {
            return 'No one is on-call this week yet — add developers to the rotation.';
        }

        $week = $assignment->week_start->format('M j');

        return "On-call this week (of {$week}): {$assignment->user->name}.";
    }
}
