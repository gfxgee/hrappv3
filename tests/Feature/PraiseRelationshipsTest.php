<?php

use App\Models\Badge;
use App\Models\Praise;
use App\Models\PraiseComment;
use App\Models\PraiseReaction;
use App\Models\PraiseSession;
use App\Models\User;

it('links a praise to its sender, recipient, session and badge', function () {
    $sender = User::factory()->create();
    $recipient = User::factory()->create();
    $session = PraiseSession::factory()->create();
    $badge = Badge::factory()->create();

    $praise = Praise::factory()->create([
        'user_id' => $sender->id,
        'recipient_id' => $recipient->id,
        'praise_session_id' => $session->id,
        'badge_id' => $badge->id,
    ]);

    expect($praise->sender->is($sender))->toBeTrue();
    expect($praise->recipient->is($recipient))->toBeTrue();
    expect($praise->praiseSession->is($session))->toBeTrue();
    expect($praise->badge->is($badge))->toBeTrue();
});

it('exposes sent and received praises on the user', function () {
    $sender = User::factory()->create();
    $recipient = User::factory()->create();

    $praise = Praise::factory()->create([
        'user_id' => $sender->id,
        'recipient_id' => $recipient->id,
    ]);

    expect($sender->praisesSent->pluck('id'))->toContain($praise->id);
    expect($recipient->praisesReceived->pluck('id'))->toContain($praise->id);
    expect($sender->praisesReceived)->toBeEmpty();
});

it('has many reactions and comments', function () {
    $praise = Praise::factory()->create();

    $reaction = PraiseReaction::factory()->create(['praise_id' => $praise->id]);
    $comment = PraiseComment::factory()->create(['praise_id' => $praise->id]);

    expect($praise->reactions->pluck('id'))->toContain($reaction->id);
    expect($praise->comments->pluck('id'))->toContain($comment->id);
    expect($reaction->praise->is($praise))->toBeTrue();
    expect($comment->praise->is($praise))->toBeTrue();
});

it('cascades deletes from a praise to its reactions and comments', function () {
    $praise = Praise::factory()->create();
    $reaction = PraiseReaction::factory()->create(['praise_id' => $praise->id]);
    $comment = PraiseComment::factory()->create(['praise_id' => $praise->id]);

    $praise->delete();

    expect(PraiseReaction::find($reaction->id))->toBeNull();
    expect(PraiseComment::find($comment->id))->toBeNull();
});

it('prevents a user from reacting to the same praise twice', function () {
    $praise = Praise::factory()->create();
    $user = User::factory()->create();

    PraiseReaction::factory()->create(['praise_id' => $praise->id, 'user_id' => $user->id]);

    expect(fn () => PraiseReaction::factory()->create([
        'praise_id' => $praise->id,
        'user_id' => $user->id,
    ]))->toThrow(Illuminate\Database\QueryException::class);
});
