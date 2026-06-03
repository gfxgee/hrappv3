<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['praise_id', 'user_id', 'comment'])]
class PraiseComment extends Model
{
    /** @use HasFactory<\Database\Factories\PraiseCommentFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Praise, $this>
     */
    public function praise(): BelongsTo
    {
        return $this->belongsTo(Praise::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
