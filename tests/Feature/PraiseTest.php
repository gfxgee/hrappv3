<?php

use App\Filament\Pages\PraiseWall;
use App\Filament\Resources\Badges\BadgeResource;
use App\Filament\Resources\Badges\Pages\CreateBadge;
use App\Filament\Resources\Badges\Pages\ListBadges;
use App\Filament\Resources\PraiseSessions\PraiseSessionResource;
use App\Models\Badge;
use App\Models\Praise;
use App\Models\PraiseComment;
use App\Models\PraiseReaction;
use App\Models\PraiseSession;
use App\Models\User;
use Database\Seeders\BadgeSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Filament::setCurrentPanel('admin');
});

function praiseUser(?string $role = null): User
{
    $user = User::factory()->create();

    if ($role !== null) {
        Role::findOrCreate($role);
        $user->assignRole($role);
    }

    return $user;
}

/*
|--------------------------------------------------------------------------
| Model behaviour
|--------------------------------------------------------------------------
*/

it('keeps only one praise session active at a time', function () {
    $first = PraiseSession::create(['name' => 'Jan', 'is_active' => true]);
    $second = PraiseSession::create(['name' => 'Feb', 'is_active' => true]);

    expect($first->fresh()->is_active)->toBeFalse()
        ->and($second->fresh()->is_active)->toBeTrue()
        ->and(PraiseSession::current()->is($second))->toBeTrue();
});

it('seeds the badge catalogue idempotently', function () {
    $this->seed(BadgeSeeder::class);
    expect(Badge::count())->toBe(14)
        ->and(Badge::where('label', 'Smooth Operator')->where('icon', '🎯')->exists())->toBeTrue();

    // Running again updates rather than duplicating.
    $this->seed(BadgeSeeder::class);
    expect(Badge::count())->toBe(14);
});

it('excludes inactive badges from the active scope', function () {
    $active = Badge::factory()->create(['is_active' => true]);
    $inactive = Badge::factory()->create(['is_active' => false]);

    $ids = Badge::active()->pluck('id');

    expect($ids)->toContain($active->id)
        ->and($ids)->not->toContain($inactive->id);
});

it('keeps a praise when its sender is deactivated (soft-deleted)', function () {
    $sender = User::factory()->create();
    $praise = Praise::factory()->create(['user_id' => $sender->id]);

    $sender->delete(); // soft delete

    $praise->refresh();
    expect(Praise::whereKey($praise->id)->exists())->toBeTrue() // praise survives
        ->and($praise->sender)->toBeNull()                       // soft-deleted sender excluded
        ->and($praise->senderName())->toBe('A former colleague');
});

/*
|--------------------------------------------------------------------------
| Praise Wall page
|--------------------------------------------------------------------------
*/

it('renders the praise wall', function () {
    $this->actingAs(praiseUser());

    Livewire::test(PraiseWall::class)->assertSuccessful();
});

it('badges the praise count for the current cycle', function () {
    $cycle = PraiseSession::create(['name' => 'Now', 'is_active' => true]);
    Praise::factory()->count(2)->create(['praise_session_id' => $cycle->id]);

    // A praise in a different (archived) cycle should not count.
    $old = PraiseSession::create(['name' => 'Old', 'is_active' => false]);
    Praise::factory()->create(['praise_session_id' => $old->id]);

    expect(PraiseWall::getNavigationBadge())->toBe('2');
});

it('posts a praise to a teammate, tagging the active session', function () {
    $me = praiseUser();
    $recipient = User::factory()->create();
    $badge = Badge::factory()->create();
    $session = PraiseSession::create(['name' => 'Q3', 'is_active' => true]);
    $this->actingAs($me);

    Livewire::test(PraiseWall::class)
        ->callAction('give', data: [
            'recipient_id' => $recipient->id,
            'badge_id' => $badge->id,
            'message' => 'Saved the release!',
        ])
        ->assertHasNoActionErrors()
        ->assertDispatched('praise-confetti');

    $praise = Praise::where('recipient_id', $recipient->id)->firstOrFail();

    expect($praise->user_id)->toBe($me->id)
        ->and($praise->badge_id)->toBe($badge->id)
        ->and($praise->praise_session_id)->toBe($session->id);
});

it('shows only the active cycle\'s praises on the wall', function () {
    $this->actingAs(praiseUser());

    $oldCycle = PraiseSession::create(['name' => 'Old', 'is_active' => false]);
    $newCycle = PraiseSession::create(['name' => 'New', 'is_active' => true]);

    $archived = Praise::factory()->create(['praise_session_id' => $oldCycle->id]);
    $current = Praise::factory()->create(['praise_session_id' => $newCycle->id]);

    $ids = Livewire::test(PraiseWall::class)->instance()->getPraises()->pluck('id');

    expect($ids)->toContain($current->id)
        ->and($ids)->not->toContain($archived->id);
});

it('lets a manager start a new cycle, archiving the current wall', function () {
    $this->actingAs(praiseUser('hr'));

    $cycle = PraiseSession::create(['name' => 'June', 'is_active' => true]);
    $old = Praise::factory()->create(['praise_session_id' => $cycle->id]);

    Livewire::test(PraiseWall::class)
        ->callAction('finishCycle', data: ['name' => 'July 2026']);

    $new = PraiseSession::where('name', 'July 2026')->firstOrFail();

    expect($new->is_active)->toBeTrue()
        ->and($cycle->fresh()->is_active)->toBeFalse();

    // The previous praise is now archived off the live wall.
    $ids = Livewire::test(PraiseWall::class)->instance()->getPraises()->pluck('id');
    expect($ids)->not->toContain($old->id);
});

it('only lets managers finish a cycle', function () {
    $this->actingAs(praiseUser('hr'));
    Livewire::test(PraiseWall::class)->assertActionVisible('finishCycle');

    $this->actingAs(praiseUser());
    Livewire::test(PraiseWall::class)->assertActionHidden('finishCycle');
});

it('does not let a user praise themselves', function () {
    $me = praiseUser();
    $this->actingAs($me);

    Livewire::test(PraiseWall::class)
        ->callAction('give', data: [
            'recipient_id' => $me->id,
            'message' => 'I am great',
        ])
        ->assertNotDispatched('praise-confetti');

    expect(Praise::where('recipient_id', $me->id)->count())->toBe(0);
});

it('toggles a reaction on and off', function () {
    $me = praiseUser();
    $this->actingAs($me);
    $praise = Praise::factory()->create();

    Livewire::test(PraiseWall::class)->call('toggleReaction', $praise->id);
    expect(PraiseReaction::where('praise_id', $praise->id)->where('user_id', $me->id)->count())->toBe(1);

    Livewire::test(PraiseWall::class)->call('toggleReaction', $praise->id);
    expect(PraiseReaction::where('praise_id', $praise->id)->where('user_id', $me->id)->count())->toBe(0);
});

it('adds a comment to a praise', function () {
    $me = praiseUser();
    $this->actingAs($me);
    $praise = Praise::factory()->create();

    Livewire::test(PraiseWall::class)
        ->set('newComment', 'Well deserved!')
        ->call('postComment', $praise->id);

    expect(PraiseComment::where('praise_id', $praise->id)
        ->where('user_id', $me->id)
        ->where('comment', 'Well deserved!')
        ->exists())->toBeTrue();
});

it('keeps the praise modal open after posting a comment', function () {
    $me = praiseUser();
    $this->actingAs($me);
    $praise = Praise::factory()->create();

    Livewire::test(PraiseWall::class)
        ->mountAction('viewPraise', ['praise' => $praise->id])
        ->assertActionMounted('viewPraise')
        ->set('newComment', 'Nice work')
        ->call('postComment', $praise->id)
        ->assertActionMounted('viewPraise')
        ->assertSet('newComment', '');

    expect(PraiseComment::where('praise_id', $praise->id)->where('comment', 'Nice work')->exists())->toBeTrue();
});

it('lets a user edit their own comment', function () {
    $me = praiseUser();
    $this->actingAs($me);
    $praise = Praise::factory()->create();
    $comment = PraiseComment::create([
        'praise_id' => $praise->id,
        'user_id' => $me->id,
        'comment' => 'original',
    ]);

    Livewire::test(PraiseWall::class)
        ->call('editComment', $comment->id)
        ->assertSet('editingCommentId', $comment->id)
        ->set('editingCommentText', 'updated text')
        ->call('updateComment', $comment->id)
        ->assertSet('editingCommentId', null);

    expect($comment->refresh()->comment)->toBe('updated text');
});

it('lets a user delete their own comment', function () {
    $me = praiseUser();
    $this->actingAs($me);
    $praise = Praise::factory()->create();
    $comment = PraiseComment::create([
        'praise_id' => $praise->id,
        'user_id' => $me->id,
        'comment' => 'bye',
    ]);

    Livewire::test(PraiseWall::class)->call('deleteComment', $comment->id);

    expect(PraiseComment::find($comment->id))->toBeNull();
});

it('does not let a user edit or delete another user\'s comment', function () {
    $me = praiseUser();
    $other = praiseUser();
    $this->actingAs($me);
    $praise = Praise::factory()->create();
    $comment = PraiseComment::create([
        'praise_id' => $praise->id,
        'user_id' => $other->id,
        'comment' => 'theirs',
    ]);

    Livewire::test(PraiseWall::class)
        ->call('editComment', $comment->id)
        ->assertSet('editingCommentId', null)
        ->call('deleteComment', $comment->id);

    expect($comment->refresh()->comment)->toBe('theirs')
        ->and(PraiseComment::find($comment->id))->not->toBeNull();
});

/*
|--------------------------------------------------------------------------
| Praise edit / delete (by the sender)
|--------------------------------------------------------------------------
*/

it('lets the sender edit their own praise', function () {
    $me = praiseUser();
    $this->actingAs($me);
    $praise = Praise::factory()->create(['user_id' => $me->id, 'message' => 'old message']);

    Livewire::test(PraiseWall::class)
        ->callAction('editPraise', data: ['message' => 'new message', 'badge_id' => null], arguments: ['praise' => $praise->id]);

    expect($praise->refresh()->message)->toBe('new message');
});

it('lets the sender delete their own praise and removes its reactions and comments', function () {
    $me = praiseUser();
    $this->actingAs($me);
    $praise = Praise::factory()->create(['user_id' => $me->id]);
    PraiseComment::create(['praise_id' => $praise->id, 'user_id' => $me->id, 'comment' => 'nice']);
    PraiseReaction::create(['praise_id' => $praise->id, 'user_id' => $me->id, 'type' => PraiseWall::REACTION]);

    Livewire::test(PraiseWall::class)
        ->callAction('deletePraise', arguments: ['praise' => $praise->id]);

    expect(Praise::find($praise->id))->toBeNull()
        ->and(PraiseComment::where('praise_id', $praise->id)->count())->toBe(0)
        ->and(PraiseReaction::where('praise_id', $praise->id)->count())->toBe(0);
});

it('does not let a user edit or delete a praise they did not send', function () {
    $me = praiseUser();
    $other = praiseUser();
    $this->actingAs($me);
    $praise = Praise::factory()->create(['user_id' => $other->id, 'message' => 'theirs']);

    Livewire::test(PraiseWall::class)
        ->callAction('editPraise', data: ['message' => 'hacked', 'badge_id' => null], arguments: ['praise' => $praise->id])
        ->callAction('deletePraise', arguments: ['praise' => $praise->id]);

    expect($praise->refresh()->message)->toBe('theirs')
        ->and(Praise::find($praise->id))->not->toBeNull();
});

/*
|--------------------------------------------------------------------------
| Wall sort / filter & cycle podium
|--------------------------------------------------------------------------
*/

it('falls back to recent for an unknown sort value', function () {
    $this->actingAs(praiseUser());

    Livewire::test(PraiseWall::class)
        ->call('setSort', 'bogus')
        ->assertSet('sort', 'recent');
});

it('sorts the wall feed by most liked', function () {
    $this->actingAs(praiseUser());

    $low = Praise::factory()->create(['praise_session_id' => null]);
    $high = Praise::factory()->create(['praise_session_id' => null]);

    PraiseReaction::create(['praise_id' => $high->id, 'user_id' => praiseUser()->id, 'type' => PraiseWall::REACTION]);
    PraiseReaction::create(['praise_id' => $high->id, 'user_id' => praiseUser()->id, 'type' => PraiseWall::REACTION]);

    $ids = Livewire::test(PraiseWall::class)
        ->set('sort', 'liked')
        ->instance()->getPraises()->pluck('id');

    expect($ids->first())->toBe($high->id);
});

it('sorts the wall feed by most commented', function () {
    $me = praiseUser();
    $this->actingAs($me);

    $quiet = Praise::factory()->create(['praise_session_id' => null]);
    $chatty = Praise::factory()->create(['praise_session_id' => null]);

    PraiseComment::create(['praise_id' => $chatty->id, 'user_id' => $me->id, 'comment' => 'a']);
    PraiseComment::create(['praise_id' => $chatty->id, 'user_id' => $me->id, 'comment' => 'b']);

    $ids = Livewire::test(PraiseWall::class)
        ->set('sort', 'commented')
        ->instance()->getPraises()->pluck('id');

    expect($ids->first())->toBe($chatty->id);
});

it('clusters the wall feed by top recipients', function () {
    $this->actingAs(praiseUser());

    $popular = praiseUser();
    $quiet = praiseUser();

    $popularPraise = Praise::factory()->create(['recipient_id' => $popular->id, 'praise_session_id' => null]);
    $quietPraise = Praise::factory()->create(['recipient_id' => $quiet->id, 'praise_session_id' => null]);

    PraiseReaction::create(['praise_id' => $popularPraise->id, 'user_id' => praiseUser()->id, 'type' => PraiseWall::REACTION]);

    $ids = Livewire::test(PraiseWall::class)
        ->set('sort', 'top_recipients')
        ->instance()->getPraises()->pluck('id');

    expect($ids->first())->toBe($popularPraise->id);
});

it('ranks the cycle podium by total reactions received', function () {
    $this->actingAs(praiseUser());

    $gold = praiseUser();
    $silver = praiseUser();
    $bronze = praiseUser();

    $goldPraise = Praise::factory()->create(['recipient_id' => $gold->id, 'praise_session_id' => null]);
    $silverPraise = Praise::factory()->create(['recipient_id' => $silver->id, 'praise_session_id' => null]);
    Praise::factory()->create(['recipient_id' => $bronze->id, 'praise_session_id' => null]);

    foreach (range(1, 3) as $i) {
        PraiseReaction::create(['praise_id' => $goldPraise->id, 'user_id' => praiseUser()->id, 'type' => PraiseWall::REACTION]);
    }
    PraiseReaction::create(['praise_id' => $silverPraise->id, 'user_id' => praiseUser()->id, 'type' => PraiseWall::REACTION]);

    $podium = Livewire::test(PraiseWall::class)->instance()->podiumForSession(null);

    expect($podium[0]['user']->id)->toBe($gold->id)
        ->and($podium[0]['reactions'])->toBe(3)
        ->and($podium[1]['user']->id)->toBe($silver->id)
        ->and($podium[1]['reactions'])->toBe(1)
        ->and($podium[2]['user']->id)->toBe($bronze->id)
        ->and($podium[2]['reactions'])->toBe(0);
});

/*
|--------------------------------------------------------------------------
| GIF comments
|--------------------------------------------------------------------------
*/

function fakeGiphy(): void
{
    config()->set('services.gif.provider', 'giphy');
    config()->set('services.gif.giphy_key', 'test-key');

    Http::fake([
        'api.giphy.com/*' => Http::response([
            'data' => [
                [
                    'id' => 'abc123',
                    'images' => [
                        'fixed_width_small' => ['url' => 'https://media.giphy.com/abc123/preview.gif'],
                        'fixed_height' => ['url' => 'https://media.giphy.com/abc123/full.gif'],
                    ],
                ],
            ],
        ]),
    ]);
}

/**
 * Fake Giphy that returns a full page (12) of unique GIFs per offset,
 * so infinite scroll can be exercised.
 */
function fakeGiphyPaged(): void
{
    config()->set('services.gif.provider', 'giphy');
    config()->set('services.gif.giphy_key', 'test-key');

    Http::fake(function ($request) {
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $params);
        $offset = (int) ($params['offset'] ?? 0);

        $data = collect(range(0, 11))->map(fn (int $i): array => [
            'id' => 'gif-'.($offset + $i),
            'images' => [
                'fixed_width_small' => ['url' => "https://media.giphy.com/{$offset}-{$i}/preview.gif"],
                'fixed_height' => ['url' => "https://media.giphy.com/{$offset}-{$i}/full.gif"],
            ],
        ])->all();

        return Http::response(['data' => $data]);
    });
}

it('loads more GIFs on demand for infinite scroll', function () {
    fakeGiphyPaged();
    $this->actingAs(praiseUser());

    $component = Livewire::test(PraiseWall::class)
        ->set('gifQuery', 'cats')
        ->assertSet('gifHasMore', true);

    expect($component->get('gifResults'))->toHaveCount(12);

    $component->call('loadMoreGifs');

    expect($component->get('gifResults'))->toHaveCount(24)
        ->and($component->get('gifOffset'))->toBe(24);
});

it('searches GIFs and normalises the provider results', function () {
    fakeGiphy();
    $this->actingAs(praiseUser());

    Livewire::test(PraiseWall::class)
        ->set('gifQuery', 'celebrate')
        ->assertSet('gifResults', [[
            'id' => 'abc123',
            'preview' => 'https://media.giphy.com/abc123/preview.gif',
            'full' => 'https://media.giphy.com/abc123/full.gif',
        ]]);
});

it('does not search for queries shorter than two characters', function () {
    fakeGiphy();
    $this->actingAs(praiseUser());

    Livewire::test(PraiseWall::class)
        ->set('gifQuery', 'a')
        ->assertSet('gifResults', []);

    Http::assertNothingSent();
});

it('returns no results when no API key is configured', function () {
    config()->set('services.gif.provider', 'giphy');
    config()->set('services.gif.giphy_key', null);
    Http::fake();
    $this->actingAs(praiseUser());

    Livewire::test(PraiseWall::class)
        ->set('gifQuery', 'celebrate')
        ->assertSet('gifResults', []);

    Http::assertNothingSent();
});

it('attaches a selected GIF to a comment', function () {
    fakeGiphy();
    $me = praiseUser();
    $this->actingAs($me);
    $praise = Praise::factory()->create();

    Livewire::test(PraiseWall::class)
        ->set('newComment', 'You rock!')
        ->set('gifQuery', 'celebrate')
        ->call('selectGif', 'https://media.giphy.com/abc123/full.gif')
        ->call('postComment', $praise->id);

    $comment = PraiseComment::where('praise_id', $praise->id)->firstOrFail();

    expect($comment->comment)->toBe('You rock!')
        ->and($comment->gif_url)->toBe('https://media.giphy.com/abc123/full.gif');
});

it('allows a GIF-only comment with no text', function () {
    fakeGiphy();
    $me = praiseUser();
    $this->actingAs($me);
    $praise = Praise::factory()->create();

    Livewire::test(PraiseWall::class)
        ->set('gifQuery', 'celebrate')
        ->call('selectGif', 'https://media.giphy.com/abc123/full.gif')
        ->call('postComment', $praise->id);

    $comment = PraiseComment::where('praise_id', $praise->id)->firstOrFail();

    expect($comment->comment)->toBeNull()
        ->and($comment->gif_url)->toBe('https://media.giphy.com/abc123/full.gif');
});

it('does not create a comment with neither text nor a GIF', function () {
    $me = praiseUser();
    $this->actingAs($me);
    $praise = Praise::factory()->create();

    Livewire::test(PraiseWall::class)
        ->call('postComment', $praise->id);

    expect(PraiseComment::where('praise_id', $praise->id)->count())->toBe(0);
});

it('ignores a GIF url that is not among the search results', function () {
    fakeGiphy();
    $this->actingAs(praiseUser());

    Livewire::test(PraiseWall::class)
        ->set('gifQuery', 'celebrate')
        ->call('selectGif', 'https://evil.example.com/not-a-result.gif')
        ->assertSet('selectedGifUrl', null);
});

/*
|--------------------------------------------------------------------------
| HR resources
|--------------------------------------------------------------------------
*/

it('restricts badge and session resources to managers', function (string $role) {
    $this->actingAs(praiseUser($role));

    expect(BadgeResource::canAccess())->toBeTrue()
        ->and(PraiseSessionResource::canAccess())->toBeTrue();
})->with(['superadmin', 'super_admin', 'hr']);

it('denies non-managers access to the badge and session resources', function () {
    $this->actingAs(praiseUser('teamleader'));
    expect(BadgeResource::canAccess())->toBeFalse();

    $this->actingAs(praiseUser());
    expect(PraiseSessionResource::canAccess())->toBeFalse();
});

it('renders and creates badges for a manager', function () {
    $this->actingAs(praiseUser('hr'));

    Livewire::test(ListBadges::class)->assertSuccessful();

    Livewire::test(CreateBadge::class)
        ->fillForm([
            'label' => 'Code Wizard',
            'color' => 'success',
            'points' => 25,
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Badge::where('label', 'Code Wizard')->where('points', 25)->exists())->toBeTrue();
});
