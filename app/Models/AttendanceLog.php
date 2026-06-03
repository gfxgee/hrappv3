<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceLog extends Model
{
    protected $guarded = [];

    public function user() : BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function hasUserClockedToday($userId, $type): bool
    {
        return self::where('user_id', $userId)
                    ->whereDate('created_at', now()->toDateString())
                    ->where('type', $type)
                    ->exists();
    }

}
