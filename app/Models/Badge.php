<?php

namespace App\Models;

use App\Models\Concerns\TracksActivity;
use Database\Factories\BadgeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['label', 'icon', 'color', 'points', 'is_active'])]
class Badge extends Model
{
    /** @use HasFactory<BadgeFactory> */
    use HasFactory;

    use TracksActivity;

    /**
     * @return list<string>
     */
    protected function activitylogFields(): array
    {
        return ['label', 'icon', 'color', 'points', 'is_active'];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'points' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<Praise, $this>
     */
    public function praises(): HasMany
    {
        return $this->hasMany(Praise::class);
    }

    /**
     * Scope to badges that are currently selectable.
     *
     * @param  Builder<Badge>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
