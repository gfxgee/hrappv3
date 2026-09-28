<x-filament-panels::page>
    <div class="text-sm text-gray-500 dark:text-gray-400">
        Upload the biometric punch export (CSV or .xlsx). Each employee's earliest scan of the day becomes
        the clock-in and the latest becomes the clock-out; repeated scans within a few minutes are collapsed
        automatically. Review and trim the rows below, then commit to the attendance logs.
    </div>

    {{ $this->table }}
</x-filament-panels::page>
