<?php

use App\Filament\Resources\OnCallAssignments\OnCallAssignmentResource;
use App\Filament\Resources\OnCallAssignments\Pages\CreateOnCallAssignment;
use App\Filament\Resources\OnCallMembers\Pages\ListOnCallMembers;
use App\Models\OnCallAssignment;
use App\Models\OnCallMember;
use App\Models\User;
use App\Services\OnCallService;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Filament::setCurrentPanel('admin');
});

function scheduleManager(string $role = 'hr'): User
{
    Role::findOrCreate($role);
    $user = User::factory()->create(['status' => 'active']);
    $user->assignRole($role);

    return $user;
}

it('lets managers access the on-call schedule and denies regular users', function () {
    $this->actingAs(scheduleManager('hr'));
    expect(OnCallAssignmentResource::canAccess())->toBeTrue();

    $this->actingAs(User::factory()->create(['status' => 'active']));
    expect(OnCallAssignmentResource::canAccess())->toBeFalse();
});

it('assigns a chosen developer to this week from the roster', function () {
    $this->actingAs(scheduleManager('hr'));

    $first = User::factory()->create(['name' => 'First Dev', 'status' => 'active']);
    $second = User::factory()->create(['name' => 'Second Dev', 'status' => 'active']);
    OnCallMember::create(['user_id' => $first->id, 'position' => 1]);
    $secondMember = OnCallMember::create(['user_id' => $second->id, 'position' => 2]);

    $service = app(OnCallService::class);
    $weekStart = $service->weekStart(today());

    // By default the rotation picks the first roster member.
    expect($service->assignmentForWeek(today())->user_id)->toBe($first->id);

    Livewire::test(ListOnCallMembers::class)
        ->callTableAction('assignThisWeek', $secondMember);

    $assignment = OnCallAssignment::whereDate('week_start', $weekStart)->sole();

    expect($assignment->user_id)->toBe($second->id)
        ->and($assignment->is_override)->toBeTrue();
});

it('does not let the weekly job overwrite a manual override', function () {
    $first = User::factory()->create(['status' => 'active']);
    $chosen = User::factory()->create(['status' => 'active']);
    OnCallMember::create(['user_id' => $first->id, 'position' => 1]);
    OnCallMember::create(['user_id' => $chosen->id, 'position' => 2]);

    $weekStart = app(OnCallService::class)->weekStart(today());
    OnCallAssignment::create([
        'week_start' => $weekStart,
        'user_id' => $chosen->id,
        'is_override' => true,
    ]);

    $this->artisan('on-call:assign')->assertSuccessful();

    expect(OnCallAssignment::whereDate('week_start', $weekStart)->value('user_id'))
        ->toBe($chosen->id);
});

it('snaps a mid-week date to that week\'s Monday when scheduling', function () {
    $this->actingAs(scheduleManager('hr'));

    $dev = User::factory()->create(['status' => 'active']);
    OnCallMember::create(['user_id' => $dev->id, 'position' => 1]);

    Livewire::test(CreateOnCallAssignment::class)
        ->fillForm([
            'week_start' => '2026-08-05', // a Wednesday
            'user_id' => $dev->id,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $assignment = OnCallAssignment::sole();

    expect($assignment->week_start->toDateString())->toBe('2026-08-03') // the Monday
        ->and($assignment->is_override)->toBeTrue();
});

it('recomputes the week after an override is reset', function () {
    $first = User::factory()->create(['name' => 'First Dev', 'status' => 'active']);
    $other = User::factory()->create(['name' => 'Other Dev', 'status' => 'active']);
    OnCallMember::create(['user_id' => $first->id, 'position' => 1]);
    OnCallMember::create(['user_id' => $other->id, 'position' => 2]);

    $service = app(OnCallService::class);
    $weekStart = $service->weekStart(today());

    $assignment = OnCallAssignment::create([
        'week_start' => $weekStart,
        'user_id' => $other->id,
        'is_override' => true,
    ]);

    expect($service->assignmentForWeek(today())->user_id)->toBe($other->id);

    // Resetting to automatic deletes the row, so the rotation decides again.
    $assignment->delete();

    expect($service->assignmentForWeek(today())->user_id)->toBe($first->id);
});
