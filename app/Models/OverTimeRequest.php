<?php

namespace App\Models;

use App\Enum\AttendanceStatus;
use App\Services\RequestNotifier;
use App\Services\TeamsNotifier;
use Database\Factories\OverTimeRequestFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OverTimeRequest extends Model
{
    /** @use HasFactory<OverTimeRequestFactory> */
    use HasFactory;

    protected $table = 'over_time_requests';

    protected $guarded = [];

    /**
     * Notify the approvers whenever a request is filed for approval.
     */
    protected static function booted(): void
    {
        static::created(function (OverTimeRequest $request): void {
            if ($request->status === AttendanceStatus::FOR_APPROVAL) {
                app(RequestNotifier::class)->overtimeFiled($request);
                app(TeamsNotifier::class)->overtimeFiled($request);
            }
        });

        // Re-notify Teams when the employee cancels or edits a pending request.
        static::updated(function (OverTimeRequest $request): void {
            if ($request->wasChanged('status')) {
                if ($request->status === AttendanceStatus::CANCELLED) {
                    app(TeamsNotifier::class)->overtimeCancelled($request);
                }

                return;
            }

            if ($request->status === AttendanceStatus::FOR_APPROVAL
                && $request->wasChanged(['request_date', 'hours', 'reason'])) {
                app(TeamsNotifier::class)->overtimeEdited($request);
            }
        });
    }

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
