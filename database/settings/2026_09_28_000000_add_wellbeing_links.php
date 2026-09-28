<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        // Optional links offered after a mood check-in. Blank hides the button.
        $this->migrator->add('general.telehealthUrl', null);
        $this->migrator->add('general.hrSupportUrl', null);
    }
};
