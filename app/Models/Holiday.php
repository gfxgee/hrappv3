<?php

namespace App\Models;

use App\Enum\HolidayDuration;
use App\Models\Concerns\TracksActivity;
use Database\Factories\HolidayFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Holiday extends Model
{
    /** @use HasFactory<HolidayFactory> */
    use HasFactory;

    use TracksActivity;

    protected $guarded = [];

    /**
     * @return list<string>
     */
    protected function activitylogFields(): array
    {
        return ['name', 'emoji', 'date', 'duration', 'is_active', 'description'];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'is_active' => 'boolean',
            'duration' => HolidayDuration::class,
        ];
    }

    /**
     * Store an empty rich-text description (e.g. "<p></p>") as null so blank
     * descriptions are consistently treated as "none".
     *
     * @return Attribute<string|null, string|null>
     */
    protected function description(): Attribute
    {
        return Attribute::set(
            fn (?string $value): ?string => filled(trim(strip_tags((string) $value))) ? $value : null,
        );
    }

    /**
     * Scope to holidays that are currently observed.
     *
     * @param  Builder<Holiday>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
