<?php

namespace App\Filament\Actions;

use App\Models\User;
use Filament\Actions\Action;

/**
 * Shared "Impersonate" action used by the Users table and the view/edit pages.
 * Authorization is enforced server-side by the package controller
 * (User::canImpersonate / canBeImpersonated); this only gates visibility.
 */
class ImpersonateAction
{
    public static function make(string $name = 'impersonate'): Action
    {
        return Action::make($name)
            ->label('Impersonate')
            ->icon('heroicon-o-user-circle')
            ->color('warning')
            ->requiresConfirmation()
            ->modalHeading(fn (User $record): string => "Impersonate {$record->name}?")
            ->modalDescription('You will browse the app as this user until you choose to stop.')
            ->visible(fn (User $record): bool => auth()->user() instanceof User
                && auth()->user()->canImpersonate()
                && ! $record->is(auth()->user())
                && $record->canBeImpersonated())
            ->action(fn (User $record) => redirect()->to(route('impersonate', $record)));
    }
}
