<?php

namespace App\Models;

use App\Enum\Mood;
use Database\Factories\MoodCheckInFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A daily mood check-in from an employee (the "moodometer"). One row per
 * employee per day; submitting again the same day updates the existing entry.
 */
class MoodCheckIn extends Model
{
    /** @use HasFactory<MoodCheckInFactory> */
    use HasFactory;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'mood' => Mood::class,
            'logged_on' => 'date',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @param  Builder<MoodCheckIn>  $query
     */
    public function scopeForToday(Builder $query): void
    {
        $query->whereDate('logged_on', today());
    }
}
