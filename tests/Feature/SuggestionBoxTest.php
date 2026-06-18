<?php

use App\Enum\SuggestionStatus;
use App\Filament\Pages\SuggestionBox;
use App\Models\Suggestion;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel('admin');
});

it('lets an employee submit a suggestion anonymously', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(SuggestionBox::class)
        ->fillForm([
            'category' => 'Workplace Environment',
            'message' => 'Please add more parking spaces.',
        ])
        ->call('submit')
        ->assertHasNoFormErrors();

    $suggestion = Suggestion::query()->firstOrFail();

    expect($suggestion->message)->toBe('Please add more parking spaces.')
        ->and($suggestion->category)->toBe('Workplace Environment')
        ->and($suggestion->status)->toBe(SuggestionStatus::NEW);
});

it('stores no reference to the author', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(SuggestionBox::class)
        ->fillForm(['message' => 'Anonymous idea.'])
        ->call('submit')
        ->assertHasNoFormErrors();

    // No column on the suggestions table may carry a user identifier.
    $columns = array_keys(Suggestion::query()->firstOrFail()->getAttributes());

    expect($columns)->not->toContain('user_id')
        ->and($columns)->not->toContain('created_by');
});

it('requires a message', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(SuggestionBox::class)
        ->fillForm(['message' => ''])
        ->call('submit')
        ->assertHasFormErrors(['message' => 'required']);

    expect(Suggestion::query()->count())->toBe(0);
});
