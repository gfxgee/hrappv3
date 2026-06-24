<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ZktecoDevice extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'last_seen' => 'datetime',
        ];
    }

    /**
     * Raw scans pushed by this physical device.
     *
     * @return HasMany<ZktecoAttendance, $this>
     */
    public function attendances(): HasMany
    {
        return $this->hasMany(ZktecoAttendance::class, 'sn', 'sn');
    }
}
