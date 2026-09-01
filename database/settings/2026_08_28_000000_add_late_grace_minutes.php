<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        // Minutes an employee may clock in past their scheduled start without
        // being marked late on the DTR.
        $this->migrator->add('general.lateGraceMinutes', 15);
    }
};
