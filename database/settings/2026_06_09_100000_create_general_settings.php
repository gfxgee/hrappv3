<?php

use App\Filament\Pages\PraiseWall;
use App\Filament\Widgets\UpcomingBirthdaysWidget;
use App\Filament\Widgets\UpcomingHolidaysWidget;
use App\Filament\Widgets\UpcomingLeavesWidget;
use App\Services\BiometricImportService;
use App\Services\DtrService;
use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        // Seed from the existing constants/config so defaults stay in one place.
        $this->migrator->add('general.lunchHours', DtrService::LUNCH_HOURS);
        $this->migrator->add('general.lunchThresholdHours', DtrService::LUNCH_THRESHOLD_HOURS);
        $this->migrator->add('general.standardWorkingHours', 8.0);
        $this->migrator->add('general.workingDays', config('leave.working_days', [1, 2, 3, 4, 5]));

        $this->migrator->add('general.birthdayWindowDays', UpcomingBirthdaysWidget::WINDOW_DAYS);
        $this->migrator->add('general.holidayWindowDays', UpcomingHolidaysWidget::WINDOW_DAYS);
        $this->migrator->add('general.leaveWindowDays', UpcomingLeavesWidget::WINDOW_DAYS);

        $this->migrator->add('general.biometricDedupeMinutes', BiometricImportService::DEFAULT_DEDUPE_MINUTES);

        $this->migrator->add('general.praiseGifPerPage', PraiseWall::GIF_PER_PAGE);
    }
};
