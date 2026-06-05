<?php

namespace App\Filament\Auth;

use Filament\Auth\Pages\EditProfile as BaseEditProfile;
use Filament\Schemas\Components\Component;

/**
 * Self-service profile: employees may change their password only. Name and
 * email are managed by HR, so they are shown read-only and never saved here.
 */
class EditProfile extends BaseEditProfile
{
    protected function getNameFormComponent(): Component
    {
        return parent::getNameFormComponent()
            ->disabled()
            ->dehydrated(false)
            ->helperText('Managed by HR — contact HR to update your name.');
    }

    protected function getEmailFormComponent(): Component
    {
        return parent::getEmailFormComponent()
            ->disabled()
            ->dehydrated(false)
            ->helperText('Managed by HR — contact HR to update your email.');
    }
}
