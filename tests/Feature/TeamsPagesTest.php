<?php

use App\Filament\Pages\DailyTimeRecord;
use App\Filament\Pages\Dashboard;
use App\Filament\Pages\FileAttendanceCorrection;
use App\Filament\Pages\FileLeaveRequest;
use App\Filament\Pages\FileOverTimeRequest;
use App\Filament\Pages\PraiseWall;
use App\Filament\Pages\SuggestionBox;
use App\Models\Announcement;
use App\Models\Praise;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Schema;

/**
 * The Teams tab's skinned pages.
 *
 * Each is the same page object as /admin's, asked for a different view. These
 * tests pin the two things that fail silently: a view that stops being
 * panel-aware (fine on /admin, wrong in the tab), and a Tailwind class that is
 * never compiled because the file it lives in is outside the theme's @source
 * globs — which looks like a layout bug and is not one.
 */
function teamsPageUser(): User
{
    $user = User::factory()->create(['status' => 'active']);

    $user->userData()->create([
        'time_in' => '09:00',
        'time_out' => '18:00',
    ]);

    return $user->fresh();
}

/** @return list<array{class: class-string, view: string, url: string}> */
function teamsSkinnedPages(): array
{
    return [
        ['class' => Dashboard::class, 'view' => 'filament.pages.teams.dashboard', 'url' => '/teams-app'],
        ['class' => DailyTimeRecord::class, 'view' => 'filament.pages.teams.daily-time-record', 'url' => '/teams-app/daily-time-record'],
        ['class' => FileLeaveRequest::class, 'view' => 'filament.pages.teams.file-leave-request', 'url' => '/teams-app/file-leave-request'],
        ['class' => FileOverTimeRequest::class, 'view' => 'filament.pages.teams.file-over-time-request', 'url' => '/teams-app/file-over-time-request'],
        ['class' => FileAttendanceCorrection::class, 'view' => 'filament.pages.teams.file-attendance-correction', 'url' => '/teams-app/file-attendance-correction'],
        ['class' => PraiseWall::class, 'view' => 'filament.pages.teams.praise-wall', 'url' => '/teams-app/praise-wall'],
        ['class' => SuggestionBox::class, 'view' => 'filament.pages.teams.suggestion-box', 'url' => '/teams-app/suggestion-box'],
    ];
}

it('asks for the Teams view in the tab and the admin view on /admin', function () {
    foreach (teamsSkinnedPages() as $page) {
        $instance = new $page['class'];

        Filament::setCurrentPanel('teams-app');
        expect($instance->getView())->toBe($page['view']);
        // The band carries the title, so Filament's heading stands down.
        expect($instance->getHeading())->toBe('');

        Filament::setCurrentPanel('admin');
        expect($instance->getView())->not->toBe($page['view'])
            ->and($instance->getHeading())->not->toBe('');
    }
});

it('renders every skinned page in the tab', function () {
    $this->actingAs(teamsPageUser());

    foreach (teamsSkinnedPages() as $page) {
        $this->get($page['url'])->assertOk();
    }
});

it('keeps everything the admin record shows on the Daily Time Record', function () {
    $this->actingAs(teamsPageUser());

    $html = $this->get('/teams-app/daily-time-record')->assertOk()->getContent();

    expect($html)
        // Both ways out of the page.
        ->toContain('Export CSV')
        ->and($html)->toContain('Print / PDF')
        // The record itself, and the totals under it.
        ->and($html)->toContain('Day by day')
        ->and($html)->toContain('Totals')
        // The month's shape, which the admin page does not show at all.
        ->and($html)->toContain('Days present')
        ->and($html)->toContain('Hours worked')
        ->and($html)->toContain('Late and undertime')
        ->and($html)->toContain('Overtime filed')
        // The print rules the admin page carries.
        ->and($html)->toContain('dtr-printable');
});

it('keeps the balance and the filed list on the leave form', function () {
    $this->actingAs(teamsPageUser());

    $html = $this->get('/teams-app/file-leave-request')->assertOk()->getContent();

    expect($html)
        ->toContain('Your balance')
        ->and($html)->toContain('My filed leaves')
        ->and($html)->toContain('File request')
        ->and($html)->toContain('What happens next');
});

it('keeps the month total and the filed list on the overtime form', function () {
    $this->actingAs(teamsPageUser());

    $html = $this->get('/teams-app/file-over-time-request')->assertOk()->getContent();

    expect($html)
        ->toContain('hours approved')
        ->and($html)->toContain('My overtime requests')
        ->and($html)->toContain('File request')
        // The shortcut that means people need not come here at all.
        ->and($html)->toContain('OT Clock Out');
});

it('states an announcement once on every page that has a band', function () {
    $this->actingAs(teamsPageUser());

    Announcement::factory()->create([
        'title' => 'Leave policy reminder',
        'message' => '<p>'.str_repeat('Policy text that is comfortably past the summary cutoff. ', 5).'</p>',
    ]);

    foreach (teamsSkinnedPages() as $page) {
        $html = $this->get($page['url'])->assertOk()->getContent();

        expect(substr_count($html, 'Read the full notice'))
            ->toBe(1, $page['url'].' drew the notice twice');
    }
});

it('lets the page say it has drawn the notice, so a re-render does not add a second', function () {
    // The original version of this matched route names, which holds on the
    // first load and fails on every Livewire update — a POST to Livewire's
    // own endpoint matches no page route, so the hook drew a second notice
    // the moment anyone opened a modal.
    $banner = file_get_contents(resource_path('views/filament/announcements/teams-banners.blade.php'));

    expect($banner)
        ->toContain('Livewire::current()')
        ->and($banner)->toContain('drawsOwnAnnouncements')
        ->and($banner)->not->toContain('routeIs');

    foreach (teamsSkinnedPages() as $page) {
        $instance = new $page['class'];

        Filament::setCurrentPanel('teams-app');
        expect($instance->drawsOwnAnnouncements())->toBeTrue();

        Filament::setCurrentPanel('admin');
        expect($instance->drawsOwnAnnouncements())->toBeFalse();
    }
});

it('scans the directories the tab draws from, so its classes are compiled', function () {
    // Tailwind emits nothing for a class it never sees, and a missed @source
    // reads as a layout bug: the band loses its padding and nothing errors.
    $theme = file_get_contents(resource_path('css/filament/teams/theme.css'));

    expect($theme)
        ->toContain("@source '../../../../resources/views/filament/**/*';")
        ->and($theme)->toContain("@source '../../../../resources/views/components/**/*';")
        ->and($theme)->toContain("@source '../../../../app/Filament/**/*';");

    // The band and the next-steps card are the files that glob exists for.
    expect(file_exists(resource_path('views/components/teams/band.blade.php')))->toBeTrue()
        ->and(file_exists(resource_path('views/components/teams/next-steps.blade.php')))->toBeTrue();
});

it('moves the praise wall header into the band, so it is not drawn twice', function () {
    $page = new PraiseWall;

    Filament::setCurrentPanel('teams-app');
    // With no heading, no subheading and no actions, Filament skips the whole
    // header block — which is what lets the band be the top of the page.
    expect($page->getSubheading())->toBeNull()
        ->and($page->getHeading())->toBe('')
        ->and((fn () => $this->getHeaderActions())->call($page))->toBe([]);

    Filament::setCurrentPanel('admin');
    expect($page->getSubheading())->toBe($page->cycleLabel())
        ->and((fn () => $this->getHeaderActions())->call($page))->toHaveCount(2);
});

it('keeps every part of the praise wall', function () {
    $user = teamsPageUser();
    $this->actingAs($user);

    // The sorts and the feed only exist once there is something to sort —
    // an empty wall is the empty state, the same as on /admin.
    Praise::factory()->create(['recipient_id' => $user->id]);

    $html = $this->get('/teams-app/praise-wall')->assertOk()->getContent();

    expect($html)
        ->toContain('Give Praise')
        // Every sort the admin wall offers.
        ->and($html)->toContain('Most recent')
        ->and($html)->toContain('Most liked')
        ->and($html)->toContain('Most commented')
        ->and($html)->toContain('Top recipients');
});

it('shares one confetti script between both walls', function () {
    // finishCycleAction calls `window.fallingConfetti?.()`, which fails in
    // silence if the view on screen is not the one that defined it.
    $shared = resource_path('views/filament/praise/confetti.blade.php');

    expect(file_exists($shared))->toBeTrue();

    $script = file_get_contents($shared);

    expect($script)
        ->toContain('window.fallingConfetti')
        ->toContain('window.boomConfetti')
        ->toContain('window.schoolPrideConfetti');

    foreach (['praise-wall', 'teams/praise-wall'] as $view) {
        expect(file_get_contents(resource_path("views/filament/pages/{$view}.blade.php")))
            ->toContain("@include('filament.praise.confetti')")
            ->and(file_get_contents(resource_path("views/filament/pages/{$view}.blade.php")))
            ->not->toContain('window.boomConfetti =');
    }
});

it('says what the suggestion box actually stores', function () {
    $this->actingAs(teamsPageUser());

    $html = $this->get('/teams-app/suggestion-box')->assertOk()->getContent();

    // The claim on the page has to match the table: there is no column for
    // who sent it, which is why the anonymity is not a matter of trust.
    expect(Schema::hasColumn('suggestions', 'user_id'))->toBeFalse();

    expect($html)
        ->toContain('This is what gets saved')
        ->and($html)->toContain('Not recorded')
        ->and($html)->toContain('Send anonymously');
});

it('never mixes inline @php() with a block @endphp in the same view', function () {
    // Blade stores `@php ... @endphp` as a raw block before it compiles
    // directives, so an inline `@php($x = ...)` pairs with the NEXT @endphp
    // anywhere later in the file. Everything between is emitted verbatim —
    // the page 500s with "Undefined variable" pointing at markup that is
    // perfectly correct. Cheap to check, miserable to debug.
    $views = array_merge(
        glob(resource_path('views/filament/pages/teams/*.blade.php')),
        glob(resource_path('views/components/teams/*.blade.php')),
        [resource_path('views/filament/announcements/teams-banners.blade.php')],
    );

    foreach ($views as $view) {
        $source = file_get_contents($view);

        $inline = preg_match('/@php\s*\(/', $source, $m, PREG_OFFSET_CAPTURE)
            ? $m[0][1]
            : null;

        $lastBlockEnd = strrpos($source, '@endphp');

        expect($inline === null || $lastBlockEnd === false || $lastBlockEnd < $inline)
            ->toBeTrue(basename($view).' mixes inline @php() with a later @endphp');
    }
});
