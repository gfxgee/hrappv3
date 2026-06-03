<?php

use App\Filament\Pages\PraiseWall;
use App\Filament\Resources\Badges\BadgeResource;
use App\Filament\Resources\Badges\Pages\ListBadges;
use App\Filament\Resources\PraiseSessions\PraiseSessionResource;
use App\Models\Badge;
use App\Models\Praise;
use App\Models\PraiseComment;
use App\Models\PraiseReaction;
use App\Models\PraiseSession;
use App\Models\User;
use Filament\Facades\Filament;
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
    $this->seed(Database\Seeders\BadgeSeeder::class);
    expect(Badge::count())->toBe(14)
        ->and(Badge::where('label', 'Smooth Operator')->where('icon', '🎯')->exists())->toBeTrue();

    // Running again updates rather than duplicating.
    $this->seed(Database\Seeders\BadgeSeeder::class);
    expect(Badge::count())->toBe(14);
});

it('excludes inactive badges from the active scope', function () {
    $active = Badge::factory()->create(['is_active' => true]);
    $inactive = Badge::factory()->create(['is_active' => false]);

    $ids = Badge::active()->pluck('id');

    expect($ids)->toContain($active->id)
        ->and($ids)->not->toContain($inactive->id);
});

it('keeps a praise when its sender is deleted', function () {
    $sender = User::factory()->create();
    $praise = Praise::factory()->create(['user_id' => $sender->id]);

    $sender->delete();

    $praise->refresh();
    expect($praise->user_id)->toBeNull()
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
        ->assertHasNoActionErrors();

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
        ->callAction('startNewCycle', data: ['name' => 'July 2026']);

    $new = PraiseSession::where('name', 'July 2026')->firstOrFail();

    expect($new->is_active)->toBeTrue()
        ->and($cycle->fresh()->is_active)->toBeFalse();

    // The previous praise is now archived off the live wall.
    $ids = Livewire::test(PraiseWall::class)->instance()->getPraises()->pluck('id');
    expect($ids)->not->toContain($old->id);
});

it('only lets managers start a new cycle', function () {
    $this->actingAs(praiseUser('hr'));
    Livewire::test(PraiseWall::class)->assertActionVisible('startNewCycle');

    $this->actingAs(praiseUser());
    Livewire::test(PraiseWall::class)->assertActionHidden('startNewCycle');
});

it('does not let a user praise themselves', function () {
    $me = praiseUser();
    $this->actingAs($me);

    Livewire::test(PraiseWall::class)
        ->callAction('give', data: [
            'recipient_id' => $me->id,
            'message' => 'I am great',
        ]);

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
        ->callAction('viewPraise', data: ['comment' => 'Well deserved!'], arguments: ['praise' => $praise->id]);

    expect(PraiseComment::where('praise_id', $praise->id)
        ->where('user_id', $me->id)
        ->where('comment', 'Well deserved!')
        ->exists())->toBeTrue();
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

    Livewire::test(App\Filament\Resources\Badges\Pages\CreateBadge::class)
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
