<?php

namespace App\Models;

use App\Enum\AssetCategory;
use App\Enum\AssetStatus;
use App\Models\Concerns\TracksActivity;
use Database\Factories\AssetFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Asset extends Model
{
    /** @use HasFactory<AssetFactory> */
    use HasFactory, SoftDeletes, TracksActivity;

    protected $guarded = [];

    /**
     * @return list<string>
     */
    protected function activitylogFields(): array
    {
        return ['asset_tag', 'category', 'name', 'brand', 'model', 'serial_number',
            'specifications', 'status', 'assigned_to', 'purchased_at', 'notes'];
    }

    /**
     * Auto-assign a sequential asset tag (AST-00001) when one isn't provided.
     */
    protected static function booted(): void
    {
        static::created(function (Asset $asset): void {
            if (blank($asset->asset_tag)) {
                $asset->forceFill(['asset_tag' => 'AST-'.str_pad((string) $asset->id, 5, '0', STR_PAD_LEFT)])
                    ->saveQuietly();
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => AssetCategory::class,
            'status' => AssetStatus::class,
            'purchased_at' => 'date',
        ];
    }

    /**
     * The employee currently holding this asset (mirrors the open assignment).
     *
     * @return BelongsTo<User, $this>
     */
    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * Full assignment/borrow history, newest first.
     *
     * @return HasMany<AssetAssignment, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(AssetAssignment::class)->latest('assigned_at');
    }

    /**
     * The open assignment, if the asset is currently held.
     *
     * @return HasOne<AssetAssignment, $this>
     */
    public function currentAssignment(): HasOne
    {
        return $this->hasOne(AssetAssignment::class)->whereNull('returned_at')->latestOfMany();
    }
}
