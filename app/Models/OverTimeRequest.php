<?php

namespace App\Models;

use App\Enum\AttendanceStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OverTimeRequest extends Model
{
    /** @use HasFactory<\Database\Factories\OverTimeRequestFactory> */
    use HasFactory;

    protected $table = 'over_time_requests';

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'request_date' => 'datetime',
            'approved_date' => 'datetime',
            'hours' => 'float',
            'status' => AttendanceStatus::class,
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
