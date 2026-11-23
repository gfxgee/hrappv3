<?php

use App\Models\Announcement;
use App\Models\User;
use Filament\Facades\Filament;

/**
 * The Teams tab draws announcements as a one-line notice that opens on a
 * click, because the full banner lands above the greeting and a long HR
 * notice pushes the whole dashboard below the fold.
 *
 * The rule these tests exist to hold: collapsing is not dropping. The full
 * message is always in the page.
 */
function renderTeamsBanners(User $user): string
{
    test()->actingAs($user);
    Filament::setCurrentPanel('teams-app');

    return view('filament.announcements.teams-banners')->render();
}

function longAnnouncementBody(): string
{
    return '<p>Employees who wish to avail of a Vacation Leave are required to file their '
        .'leave request at least two weeks before the intended leave date.</p>'
        .'<ul><li>Playing video games during working hours</li>'
        .'<li>Streaming non-work-related content</li></ul>'
        .'<p>Thank you for your understanding and continued commitment to the team.</p>';
}

it('collapses a long announcement but keeps the whole message in the page', function () {
    $user = User::factory()->create(['status' => 'active']);

    Announcement::factory()->create([
        'title' => 'Leave policy reminder',
        'message' => longAnnouncementBody(),
    ]);

    $html = renderTeamsBanners($user);

    expect($html)
        ->toContain('Leave policy reminder')
        // The toggle, and the body it reveals — not a truncated copy of it.
        ->and($html)->toContain('Read the full notice')
        ->and($html)->toContain('Streaming non-work-related content')
        ->and($html)->toContain('Thank you for your understanding')
        // Collapsed on arrival.
        ->and($html)->toContain('open: false');
});

it('shows a short announcement in full, with nothing to click', function () {
    $user = User::factory()->create(['status' => 'active']);

    Announcement::factory()->create([
        'title' => 'Payday moved',
        'message' => '<p>Payday is Thursday this week.</p>',
    ]);

    $html = renderTeamsBanners($user);

    expect($html)
        ->toContain('Payday is Thursday this week.')
        ->and($html)->not->toContain('Read the full notice')
        ->and($html)->toContain('open: true');
});

it('leaves an urgent alert open and undismissable', function () {
    $user = User::factory()->create(['status' => 'active']);

    Announcement::factory()->urgent()->create([
        'title' => 'Office closed',
        'message' => longAnnouncementBody(),
    ]);

    $html = renderTeamsBanners($user);

    expect($html)
        ->toContain('Office closed')
        ->toContain('Thank you for your understanding')
        // No collapse, no dismiss: that is what urgent means.
        ->and($html)->not->toContain('Read the full notice')
        ->and($html)->not->toContain('Dismiss Office closed');
});

it('summarises the body as prose rather than running words together', function () {
    $user = User::factory()->create(['status' => 'active']);

    Announcement::factory()->create([
        'title' => 'Two paragraphs',
        'message' => '<p>'.str_repeat('First paragraph. ', 10).'</p><p>Second paragraph.</p>',
    ]);

    $html = renderTeamsBanners($user);

    // Block tags become spaces before the tags are stripped, so the last word
    // of one paragraph does not weld itself to the first of the next.
    expect($html)->not->toContain('paragraph.Second');
});

it('keeps the full banner on /admin and the compact one in the tab', function () {
    $teams = file_get_contents(app_path('Providers/Filament/TeamsPanelProvider.php'));
    $admin = file_get_contents(app_path('Providers/Filament/AdminPanelProvider.php'));

    expect($teams)->toContain('filament.announcements.teams-banners')
        ->and($admin)->not->toContain('teams-banners');
});

it('gives both themes the announcement prose styles', function () {
    // Without these, the rich text collapses into one flat wall: Preflight
    // strips the editor's headings, lists and paragraph spacing. The Teams
    // theme shipped without them, which is why it read as a block of text.
    $shared = resource_path('css/announcement-prose.css');

    expect(file_exists($shared))->toBeTrue();

    foreach (['admin', 'teams'] as $panel) {
        expect(file_get_contents(resource_path("css/filament/{$panel}/theme.css")))
            ->toContain('announcement-prose.css');
    }

    expect(file_get_contents($shared))->toContain('.fi-announcement-body ul');
});

it('puts the notice under the greeting on the dashboard, and states it once', function () {
    $user = User::factory()->create(['status' => 'active']);
    $user->userData()->create(['time_in' => '09:00', 'time_out' => '18:00']);

    Announcement::factory()->create([
        'title' => 'Leave policy reminder',
        'message' => longAnnouncementBody(),
    ]);

    $html = $this->actingAs($user->fresh())->get('/teams-app')->assertOk()->getContent();

    // Once, not twice: the panel hook stands down on this page because the
    // view draws it under the band. Counted on the toggle, which appears
    // exactly once per notice — the message text itself appears twice by
    // design, as the one-line summary and as the body it opens.
    expect(substr_count($html, 'Read the full notice'))->toBe(1);

    $greeting = strpos($html, 'Good ');
    $notice = strpos($html, 'Leave policy reminder');

    expect($greeting)->toBeLessThan($notice);
});

it('still shows the notice at the top of the tab\'s other pages', function () {
    $user = User::factory()->create(['status' => 'active']);
    $user->userData()->create(['time_in' => '09:00', 'time_out' => '18:00']);

    Announcement::factory()->create([
        'title' => 'Leave policy reminder',
        'message' => longAnnouncementBody(),
    ]);

    // There is no greeting band on these, so the panel hook keeps drawing it.
    $this->actingAs($user->fresh())
        ->get('/teams-app/daily-time-record')
        ->assertOk()
        ->assertSee('Leave policy reminder');
});
