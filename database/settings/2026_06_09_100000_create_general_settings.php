<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        // Literal defaults — intentionally not referencing app classes so this
        // migration stays runnable as the codebase evolves.
        $this->migrator->add('general.lunchHours', 1.0);
        $this->migrator->add('general.lunchThresholdHours', 5.0);
        $this->migrator->add('general.standardWorkingHours', 8.0);
        $this->migrator->add('general.workingDays', [1, 2, 3, 4, 5]);

        $this->migrator->add('general.birthdayWindowDays', 45);
        $this->migrator->add('general.holidayWindowDays', 90);
        $this->migrator->add('general.leaveWindowDays', 60);

        $this->migrator->add('general.biometricDedupeMinutes', 3);

        $this->migrator->add('general.praiseGifPerPage', 12);
    }
};
