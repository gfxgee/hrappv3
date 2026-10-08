<?php

use App\Enum\Mood;
use App\Filament\Widgets\MoodCheckInWidget;
use App\Models\User;
use App\Rules\SupportLink;
use App\Settings\GeneralSettings;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel('admin');
});

function supportLinkPasses(?string $value): bool
{
    return ! validator(['link' => $value], ['link' => [new SupportLink]])->fails();
}

it('accepts web, email, and phone links', function (string $value) {
    expect(supportLinkPasses($value))->toBeTrue();
})->with([
    'mailto:atheena@digitalfeet.com',
    'mailto:hr@digitalfeet.com?subject=Support%20request',
    'https://hr.example.com/support',
    'http://intranet.local/hr',
    'tel:+639171234567',
]);

it('rejects unsafe or malformed links', function (string $value) {
    expect(supportLinkPasses($value))->toBeFalse();
})->with([
    'javascript:alert(1)',          // would be clickable in the href
    'data:text/html,<script>',      // ditto
    'mailto:not-an-email',
    'tel:abc',
    'just some text',
]);

it('treats a blank link as valid so the button can be hidden', function () {
    expect(supportLinkPasses(null))->toBeTrue()
        ->and(supportLinkPasses(''))->toBeTrue();
});

it('offers a mailto HR link on a stressed check-in', function () {
    app(GeneralSettings::class)->fill(['hrSupportUrl' => 'mailto:atheena@digitalfeet.com'])->save();

    $this->actingAs(User::factory()->create(['status' => 'active']));

    $panel = Livewire::test(MoodCheckInWidget::class)
        ->call('logMood', Mood::STRESSED->value)
        ->instance()
        ->followUpPanel();

    expect($panel['link_url'])->toBe('mailto:atheena@digitalfeet.com')
        // A mailto hands off to the mail app — no stray blank tab.
        ->and($panel['link_opens_new_tab'])->toBeFalse();
});

it('opens a web support link in a new tab', function () {
    app(GeneralSettings::class)->fill(['hrSupportUrl' => 'https://hr.example.com/support'])->save();

    $this->actingAs(User::factory()->create(['status' => 'active']));

    $panel = Livewire::test(MoodCheckInWidget::class)
        ->call('logMood', Mood::STRESSED->value)
        ->instance()
        ->followUpPanel();

    expect($panel['link_opens_new_tab'])->toBeTrue();
});

it('accepts a mailto telehealth link too', function () {
    app(GeneralSettings::class)->fill(['telehealthUrl' => 'mailto:clinic@digitalfeet.com'])->save();

    $this->actingAs(User::factory()->create(['status' => 'active']));

    $panel = Livewire::test(MoodCheckInWidget::class)
        ->call('logMood', Mood::SICK->value)
        ->instance()
        ->followUpPanel();

    expect($panel['link_url'])->toBe('mailto:clinic@digitalfeet.com')
        ->and($panel['link_opens_new_tab'])->toBeFalse();
});
