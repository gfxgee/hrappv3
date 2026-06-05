<?php

namespace App\Models;

use Database\Factories\PraiseCommentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['praise_id', 'user_id', 'comment', 'gif_url'])]
class PraiseComment extends Model
{
    /** @use HasFactory<PraiseCommentFactory> */
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
