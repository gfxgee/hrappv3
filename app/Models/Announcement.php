<?php

namespace App\Models;

use App\Enum\AnnouncementType;
use App\Models\Concerns\TracksActivity;
use Database\Factories\AnnouncementFactory;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * An admin notice shown as a banner across the panel. Visible while active and
 * within its optional date window, to everyone — or only to members of its
 * targeted departments when any are attached.
 */
class Announcement extends Model
{
    /** @use HasFactory<AnnouncementFactory> */
    use HasFactory;

    use TracksActivity;

    protected $guarded = [];

    /**
     * @return list<string>
     */
    protected function activitylogFields(): array
    {
        return ['title', 'message', 'type', 'is_active', 'is_urgent', 'starts_at', 'ends_at'];
    }

    /**
     * Notify every active employee when an announcement becomes an active urgent
     * alert — either created that way or toggled urgent later.
     */
    protected static function booted(): void
    {
        static::created(function (Announcement $announcement): void {
            if ($announcement->is_urgent && $announcement->is_active) {
                $announcement->notifyUrgentAlert();
            }
        });

        // Toggling an existing announcement to urgent (while active) also alerts.
        static::updated(function (Announcement $announcement): void {
            if ($announcement->is_urgent && $announcement->is_active && $announcement->wasChanged('is_urgent')) {
                $announcement->notifyUrgentAlert();
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => AnnouncementType::class,
            'is_active' => 'boolean',
            'is_urgent' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    /**
     * Push an in-app notification of this alert to every active employee so it
     * surfaces in the notification bell, not just the on-screen banner.
     */
    public function notifyUrgentAlert(): void
    {
        $recipients = User::query()->active()->get();

        if ($recipients->isEmpty()) {
            return;
        }

        $body = Str::limit(trim(strip_tags((string) $this->message)), 200);

        Notification::make()
            ->title($this->title ? "🚨 {$this->title}" : '🚨 Urgent alert')
            ->icon('heroicon-o-exclamation-triangle')
            ->iconColor('danger')
            ->body($body)
            ->sendToDatabase($recipients, isEventDispatched: true);
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
