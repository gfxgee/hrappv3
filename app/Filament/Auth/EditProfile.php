<?php

namespace App\Filament\Auth;

use App\Filament\Support\GovernmentDocumentsRepeater;
use App\Filament\Support\PcSpecificationsRepeater;
use Filament\Auth\Pages\EditProfile as BaseEditProfile;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Self-service profile: employees may change their password, link the digital
 * copies of their government IDs, and maintain their PC specifications. Name
 * and email are managed by HR, so they are shown read-only and never saved here.
 */
class EditProfile extends BaseEditProfile
{
    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getNameFormComponent(),
                $this->getEmailFormComponent(),
                $this->getPasswordFormComponent(),
                $this->getPasswordConfirmationFormComponent(),
                $this->getCurrentPasswordFormComponent(),
                Section::make('Government ID Documents')
                    ->description('Link to your digital copies (e.g. Google Drive or OneDrive). HR can also view and update these.')
                    ->schema([
                        GovernmentDocumentsRepeater::make(),
                    ]),
                Section::make('PC Specifications')
                    ->description('Your workstation hardware. Keep this up to date so IT/HR have an accurate record.')
                    ->schema([
                        PcSpecificationsRepeater::make(),
                    ]),
            ]);
    }

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
