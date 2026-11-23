<?php

use App\Filament\Pages\PraiseWall;
use App\Models\Praise;
use App\Models\User;
use Filament\Facades\Filament;

/**
 * An opened praise is the one dialog the Teams tab shows, and it has its own
 * drawing. The risk worth pinning is drift: two views over one component, so
 * a control added to one and not the other goes unnoticed until someone in
 * the tab cannot delete their own comment.
 */
function praiseForDetail(): array
{
    $user = User::factory()->create(['status' => 'active']);
    $user->userData()->create(['time_in' => '09:00', 'time_out' => '18:00']);

    $praise = Praise::factory()->create(['recipient_id' => $user->id]);

    return [$user->fresh(), $praise];
}

it("opens the tab's own dialog, and /admin's on /admin", function () {
    [$user, $praise] = praiseForDetail();
    $this->actingAs($user);

    // The modal body renders in a lazy partial, so ask the action which view
    // it is configured to show rather than scraping the page.
    $contentView = function (int $id): string {
        $action = (new PraiseWall)->viewPraiseAction();
        $action->arguments(['praise' => $id]);

        return $action->getModalContent()->name();
    };

    Filament::setCurrentPanel('teams-app');
    expect($contentView($praise->id))->toBe('filament.praise.teams-detail');

    Filament::setCurrentPanel('admin');
    expect($contentView($praise->id))->toBe('filament.praise.detail');
});

it('keeps every control the admin dialog has', function () {
    $admin = file_get_contents(resource_path('views/filament/praise/detail.blade.php'));
    $teams = file_get_contents(resource_path('views/filament/praise/teams-detail.blade.php'));

    // Reacting, editing and deleting your own comment, the GIF picker and
    // the AI rewrite are the whole of what this dialog can do.
    $controls = [
        'toggleReaction',
        'editComment',
        'confirmDeleteComment',
        'updateComment',
        'cancelEditComment',
        'enhanceNewComment',
        'canEnhanceComment',
        'postComment',
        'ownsComment',
        'editingCommentText',
        'newComment',
        'gif_url',
        'filament.praise.gif-picker',
    ];

    foreach ($controls as $control) {
        expect($admin)->toContain($control)
            ->and($teams)->toContain($control);
    }
});

it('drops the footer Close button in the tab, where the corner × is the way out', function () {
    [$user] = praiseForDetail();
    $this->actingAs($user);

    Filament::setCurrentPanel('teams-app');
    expect((new PraiseWall)->viewPraiseAction()->getModalCancelAction())->toBeNull();

    Filament::setCurrentPanel('admin');
    expect((new PraiseWall)->viewPraiseAction()->getModalCancelAction())->not->toBeNull();
});

it('lets the Teams composer restyle the shared GIF trigger', function () {
    // The picker is shared, so the tab passes its own button classes rather
    // than forking the partial.
    $picker = file_get_contents(resource_path('views/filament/praise/gif-picker.blade.php'));
    $teams = file_get_contents(resource_path('views/filament/praise/teams-detail.blade.php'));

    expect($picker)->toContain('$triggerClass ??')
        ->and($picker)->toContain('$triggerLabel ??')
        ->and($teams)->toContain("'triggerClass' =>");
});
