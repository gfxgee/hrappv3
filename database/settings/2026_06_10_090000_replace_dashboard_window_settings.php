<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        // The three per-widget windows became dead knobs when the dashboard
        // redesign merged those widgets into the single "Coming up" widget.
        $this->migrator->add('general.comingUpWindowDays', 14);

        $this->migrator->deleteIfExists('general.birthdayWindowDays');
        $this->migrator->deleteIfExists('general.holidayWindowDays');
        $this->migrator->deleteIfExists('general.leaveWindowDays');
    }
};
