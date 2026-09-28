<?php

use App\Enum\Mood;
use App\Filament\Widgets\MoodCheckInWidget;
use App\Models\MoodCheckIn;
use App\Models\User;
use App\Settings\GeneralSettings;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel('admin');
});

it('offers breathing and a micro-break after a stressed check-in', function () {
    app(GeneralSettings::class)->fill(['hrSupportUrl' => 'https://hr.example.com/support'])->save();

    $this->actingAs(User::factory()->create(['status' => 'active']));

    $panel = Livewire::test(MoodCheckInWidget::class)
        ->call('logMood', Mood::STRESSED->value)
        ->assertSet('followUp', Mood::STRESSED->value)
        ->instance()
        ->followUpPanel();

    expect($panel['breathing'])->toBeTrue()
        ->and($panel['break_minutes'])->toBe(2)
        ->and($panel['link_url'])->toBe('https://hr.example.com/support');
});

it('offers a 10 minute micro-break after a tired check-in', function () {
    $this->actingAs(User::factory()->create(['status' => 'active']));

    $panel = Livewire::test(MoodCheckInWidget::class)
        ->call('logMood', Mood::TIRED->value)
        ->instance()
        ->followUpPanel();

    expect($panel['breathing'])->toBeFalse()
        ->and($panel['break_minutes'])->toBe(10);
});

it('offers the telehealth link after a sick check-in', function () {
    app(GeneralSettings::class)->fill(['telehealthUrl' => 'https://care.example.com'])->save();

    $this->actingAs(User::factory()->create(['status' => 'active']));

    $panel = Livewire::test(MoodCheckInWidget::class)
        ->call('logMood', Mood::SICK->value)
        ->instance()
        ->followUpPanel();

    expect($panel['link_url'])->toBe('https://care.example.com')
        ->and($panel['note'])->toContain('notified');
});

it('shows no follow-up for happy or calm', function () {
    $this->actingAs(User::factory()->create(['status' => 'active']));

    foreach ([Mood::HAPPY, Mood::CALM] as $mood) {
        $panel = Livewire::test(MoodCheckInWidget::class)
            ->call('logMood', $mood->value)
            ->assertSet('followUp', null)
            ->instance()
            ->followUpPanel();

        expect($panel)->toBeNull();
    }
});

it('hides the link button when no url is configured', function () {
    app(GeneralSettings::class)->fill(['telehealthUrl' => null])->save();

    $this->actingAs(User::factory()->create(['status' => 'active']));

    $panel = Livewire::test(MoodCheckInWidget::class)
        ->call('logMood', Mood::SICK->value)
        ->instance()
        ->followUpPanel();

    expect($panel['link_url'])->toBeNull();
});

it('still records the mood when a follow-up is shown', function () {
    $user = User::factory()->create(['status' => 'active']);
    $this->actingAs($user);

    Livewire::test(MoodCheckInWidget::class)->call('logMood', Mood::STRESSED->value);

    expect(MoodCheckIn::where('user_id', $user->id)->value('mood'))->toBe(Mood::STRESSED);
});

it('dismissing the follow-up clears it', function () {
    $this->actingAs(User::factory()->create(['status' => 'active']));

    Livewire::test(MoodCheckInWidget::class)
        ->call('logMood', Mood::TIRED->value)
        ->assertSet('followUp', Mood::TIRED->value)
        ->call('dismissFollowUp')
        ->assertSet('followUp', null);
});

it('treats calm as not needing attention and tired as needing it', function () {
    expect(Mood::CALM->needsAttention())->toBeFalse()
        ->and(Mood::HAPPY->needsAttention())->toBeFalse()
        ->and(Mood::TIRED->needsAttention())->toBeTrue()
        ->and(Mood::STRESSED->needsAttention())->toBeTrue()
        ->and(Mood::SICK->needsAttention())->toBeTrue();
});
