<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        // Maximum overtime an employee may file per request.
        $this->migrator->add('general.maxOvertimeHours', 5.0);
    }
};
