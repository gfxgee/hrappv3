<?php

namespace App\Models\Concerns;

use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Records create/update/delete to the activity log for the columns the model
 * declares in activitylogFields(). Only changed fields are stored, empty diffs
 * are skipped, and everything lands under the "hr" log name.
 */
trait TracksActivity
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly($this->activitylogFields())
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('hr');
    }

    /**
     * Columns to record. Keep this an explicit allow-list — never include
     * secrets (passwords, tokens).
     *
     * @return list<string>
     */
    abstract protected function activitylogFields(): array;
}
