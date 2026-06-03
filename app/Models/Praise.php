<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'recipient_id', 'praise_session_id', 'badge_id', 'message'])]
class Praise extends Model
{
    /** @use HasFactory<\Database\Factories\PraiseFactory> */
    use HasFactory;

    /**
     * The user who sent the praise.
     *
     * @return BelongsTo<User, $this>
     */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * The user who received the praise.
     *
     * @return BelongsTo<User, $this>
     */
    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }

    /**
     * Display name for the sender, accounting for a since-deleted sender.
     */
    public function senderName(): string
    {
        return $this->sender?->name ?? 'A former colleague';
    }

    /**
     * @return BelongsTo<PraiseSession, $this>
     */
    public function praiseSession(): BelongsTo
    {
        return $this->belongsTo(PraiseSession::class);
    }

    /**
     * @return BelongsTo<Badge, $this>
     */
    public function badge(): BelongsTo
    {
        return $this->belongsTo(Badge::class);
    }

    /**
     * @return HasMany<PraiseReaction, $this>
     */
    public function reactions(): HasMany
    {
        return $this->hasMany(PraiseReaction::class);
    }

    /**
     * @return HasMany<PraiseComment, $this>
     */
    public function comments(): HasMany
    {
        return $this->hasMany(PraiseComment::class);
    }
}
