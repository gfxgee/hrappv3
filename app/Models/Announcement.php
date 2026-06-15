<?php

namespace App\Models;

use App\Enum\AnnouncementType;
use Database\Factories\AnnouncementFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;

/**
 * An admin notice shown as a banner across the panel. Visible while active and
 * within its optional date window, to everyone — or only to members of its
 * targeted departments when any are attached.
 */
class Announcement extends Model
{
    /** @use HasFactory<AnnouncementFactory> */
    use HasFactory;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => AnnouncementType::class,
            'is_active' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    /**
     * Departments this announcement targets. Empty means everyone.
     *
     * @return BelongsToMany<Department, $this>
     */
    public function departments(): BelongsToMany
    {
        return $this->belongsToMany(Department::class);
    }

    /**
     * @param  Builder<Announcement>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * Within the optional [starts_at, ends_at] window (null bounds are open).
     *
     * @param  Builder<Announcement>  $query
     */
    public function scopeInWindow(Builder $query): void
    {
        $now = now();

        $query->where(fn (Builder $q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
            ->where(fn (Builder $q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', $now));
    }

    /**
     * Announcements that should currently show to the given user: active,
     * in-window, and either untargeted (everyone) or targeting the user's
     * department. Newest first.
     *
     * @return Collection<int, Announcement>
     */
    public static function liveForUser(?User $user): Collection
    {
        $departmentId = $user?->department_id;

        return self::query()
            ->active()
            ->inWindow()
            ->with('departments')
            ->where(function (Builder $query) use ($departmentId): void {
                $query->whereDoesntHave('departments');

                if ($departmentId !== null) {
                    $query->orWhereHas('departments', fn (Builder $q) => $q->whereKey($departmentId));
                }
            })
            ->latest()
            ->get();
    }
}
