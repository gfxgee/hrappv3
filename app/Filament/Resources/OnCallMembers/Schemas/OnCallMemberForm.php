<?php

namespace App\Filament\Resources\OnCallMembers\Schemas;

use App\Enum\UserStatus;
use App\Models\OnCallMember;
use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class OnCallMemberForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('user_id')
                    ->label('Developer')
                    ->relationship(
                        'user',
                        'name',
                        // Offer active users not already on the roster — but keep
                        // the record's own user selectable when editing, otherwise
                        // its option label can't resolve and shows the raw id.
                        fn (Builder $query, ?Model $record): Builder => $query
                            ->where('status', UserStatus::ACTIVE->value)
                            ->whereNotIn('id', OnCallMember::query()
                                ->when($record, fn (Builder $roster): Builder => $roster->whereKeyNot($record->getKey()))
                                ->pluck('user_id'))
                            ->orderBy('name'),
                    )
                    ->searchable()
                    ->preload()
                    ->required()
                    ->unique(ignoreRecord: true),
            ]);
    }
}
