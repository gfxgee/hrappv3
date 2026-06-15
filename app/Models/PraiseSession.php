<?php

namespace App\Models;

use App\Models\Concerns\TracksActivity;
use Database\Factories\PraiseSessionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'is_active'])]
class PraiseSession extends Model
{
    /** @use HasFactory<PraiseSessionFactory> */
    use HasFactory;

    use TracksActivity;

    /**
     * @return list<string>
     */
    protected function activitylogFields(): array
    {
        return ['name', 'is_active'];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * Ensure only one session is active at a time: activating one deactivates
     * the rest. Uses a mass update (no model events) so there's no recursion.
     */
    protected static function booted(): void
    {
        static::saved(function (PraiseSession $session): void {
            if ($session->is_active) {
                static::query()
                    ->whereKeyNot($session->getKey())
                    ->where('is_active', true)
                    ->update(['is_active' => false]);
            }
        });
    }

    /**
     * The currently active award cycle, if any.
     */
    public static function current(): ?self
    {
        return static::query()->where('is_active', true)->latest('id')->first();
    }

    /**
     * @return HasMany<Praise, $this>
     */
    public function praises(): HasMany
    {
        return $this->hasMany(Praise::class);
    }
}
