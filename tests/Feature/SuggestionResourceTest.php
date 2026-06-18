<?php

use App\Enum\SuggestionStatus;
use App\Filament\Resources\Suggestions\Pages\ListSuggestions;
use App\Filament\Resources\Suggestions\SuggestionResource;
use App\Models\Suggestion;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Filament::setCurrentPanel('admin');
});

function suggestionManager(string $role): User
{
    Role::findOrCreate($role);
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

it('allows manager roles to access the suggestion resource', function (string $role) {
    $this->actingAs(suggestionManager($role));

    expect(SuggestionResource::canAccess())->toBeTrue();
})->with(['superadmin', 'super_admin', 'hr']);

it('denies regular users access to the suggestion resource', function () {
    $this->actingAs(User::factory()->create());

    expect(SuggestionResource::canAccess())->toBeFalse();
});

it('never allows creating suggestions from the panel', function () {
    expect(SuggestionResource::canCreate())->toBeFalse();
});

it('renders the suggestion list for a manager', function () {
    $this->actingAs(suggestionManager('hr'));
    Suggestion::factory()->count(3)->create();

    Livewire::test(ListSuggestions::class)->assertSuccessful();
});

it('badges only unreviewed suggestions', function () {
    $this->actingAs(suggestionManager('hr'));

    expect(SuggestionResource::getNavigationBadge())->toBeNull();

    Suggestion::factory()->count(2)->create(); // new
    Suggestion::factory()->status(SuggestionStatus::REVIEWED)->create();
    Suggestion::factory()->status(SuggestionStatus::DISMISSED)->create();

    expect(SuggestionResource::getNavigationBadge())->toBe('2');
});

it('lets a manager triage a suggestion by changing its status', function () {
    $this->actingAs(suggestionManager('hr'));
    $suggestion = Suggestion::factory()->create();

    expect($suggestion->status)->toBe(SuggestionStatus::NEW);

    $suggestion->update(['status' => SuggestionStatus::ACTIONED]);

    expect($suggestion->fresh()->status)->toBe(SuggestionStatus::ACTIONED);
});
