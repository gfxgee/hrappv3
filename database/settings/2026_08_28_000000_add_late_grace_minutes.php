<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        // Minutes deducted from a late clock-in before it counts as tardiness
        // on the DTR.
        $this->migrator->add('general.lateGraceMinutes', 15);
    }
};
