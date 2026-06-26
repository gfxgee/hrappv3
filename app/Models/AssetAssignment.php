<?php

namespace App\Models;

use App\Enum\AssignmentType;
use App\Models\Concerns\TracksActivity;
use Database\Factories\AssetAssignmentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetAssignment extends Model
{
    /** @use HasFactory<AssetAssignmentFactory> */
    use HasFactory, TracksActivity;

    protected $guarded = [];

    /**
     * @return list<string>
     */
    protected function activitylogFields(): array
    {
        return ['asset_id', 'user_id', 'type', 'assigned_at', 'due_at', 'returned_at',
            'assigned_by', 'received_by', 'notes'];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => AssignmentType::class,
            'assigned_at' => 'datetime',
            'due_at' => 'datetime',
            'returned_at' => 'datetime',
        ];
    }

    /**
     * Assignments that are still open (the asset has not been returned).
     *
     * @param  Builder<AssetAssignment>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->whereNull('returned_at');
    }

    /**
     * Whether the asset is still held under this assignment.
     */
    public function isOpen(): bool
    {
        return $this->returned_at === null;
    }

    /**
     * @return BelongsTo<Asset, $this>
     */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    /**
     * The employee holding the asset.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The admin who handed the asset out.
     *
     * @return BelongsTo<User, $this>
     */
    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    /**
     * The admin who processed the return.
     *
     * @return BelongsTo<User, $this>
     */
    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }
}
