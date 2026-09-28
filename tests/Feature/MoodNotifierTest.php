<?php

use App\Enum\Mood;
use App\Filament\Widgets\MoodCheckInWidget;
use App\Models\Department;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Filament::setCurrentPanel('admin');
});

function hrStaff(): User
{
    Role::findOrCreate('hr');
    $hr = User::factory()->create(['status' => 'active']);
    $hr->assignRole('hr');

    return $hr;
}

it('notifies the manager and HR when someone checks in as sick', function () {
    $hr = hrStaff();
    $manager = User::factory()->create(['status' => 'active']);
    $employee = User::factory()->create(['status' => 'active', 'manager_id' => $manager->id]);

    $this->actingAs($employee);

    Livewire::test(MoodCheckInWidget::class)->call('logMood', Mood::SICK->value);

    expect($manager->fresh()->notifications()->count())->toBe(1)
        ->and($hr->fresh()->notifications()->count())->toBe(1)
        ->and($employee->fresh()->notifications()->count())->toBe(0);
});

it('uses neutral wording that does not leak a reason', function () {
    $manager = User::factory()->create(['status' => 'active']);
    $employee = User::factory()->create([
        'name' => 'Sam Cruz',
        'display_name' => 'Sam',
        'status' => 'active',
        'manager_id' => $manager->id,
    ]);

    $this->actingAs($employee);

    Livewire::test(MoodCheckInWidget::class)->call('logMood', Mood::SICK->value);

    $body = $manager->fresh()->notifications()->first()->data['body'] ?? '';

    expect($body)->toContain('Sam')
        ->and($body)->toContain('offline');
});

it('does not notify twice when sick is re-selected the same day', function () {
    $manager = User::factory()->create(['status' => 'active']);
    $employee = User::factory()->create(['status' => 'active', 'manager_id' => $manager->id]);

    $this->actingAs($employee);

    Livewire::test(MoodCheckInWidget::class)
        ->call('logMood', Mood::SICK->value)
        ->call('logMood', Mood::SICK->value);

    expect($manager->fresh()->notifications()->count())->toBe(1);
});

it('notifies again when the mood changes away from sick and back', function () {
    $manager = User::factory()->create(['status' => 'active']);
    $employee = User::factory()->create(['status' => 'active', 'manager_id' => $manager->id]);

    $this->actingAs($employee);

    Livewire::test(MoodCheckInWidget::class)
        ->call('logMood', Mood::SICK->value)
        ->call('logMood', Mood::HAPPY->value)
        ->call('logMood', Mood::SICK->value);

    expect($manager->fresh()->notifications()->count())->toBe(2);
});

it('does not notify for non-sick moods', function () {
    $hr = hrStaff();
    $manager = User::factory()->create(['status' => 'active']);
    $employee = User::factory()->create(['status' => 'active', 'manager_id' => $manager->id]);

    $this->actingAs($employee);

    foreach ([Mood::HAPPY, Mood::CALM, Mood::STRESSED, Mood::TIRED] as $mood) {
        Livewire::test(MoodCheckInWidget::class)->call('logMood', $mood->value);
    }

    expect($manager->fresh()->notifications()->count())->toBe(0)
        ->and($hr->fresh()->notifications()->count())->toBe(0);
});

it('notifies department leaders when there is no direct manager', function () {
    $department = Department::factory()->create();
    $leader = User::factory()->create(['status' => 'active']);
    $leader->ledDepartments()->attach($department);

    $employee = User::factory()->create([
        'status' => 'active',
        'department_id' => $department->id,
        'manager_id' => null,
    ]);

    $this->actingAs($employee);

    Livewire::test(MoodCheckInWidget::class)->call('logMood', Mood::SICK->value);

    expect($leader->fresh()->notifications()->count())->toBe(1);
});

it('posts a sick check-in to the Teams group chat', function () {
    config()->set('services.teams.flow_url', 'https://flow.test/invoke');
    Http::fake();

    $employee = User::factory()->create([
        'name' => 'Sam Cruz',
        'display_name' => 'Sam',
        'status' => 'active',
    ]);
    $this->actingAs($employee);

    Livewire::test(MoodCheckInWidget::class)->call('logMood', Mood::SICK->value);

    Http::assertSent(function (Request $request): bool {
        $body = $request->data();

        return $request->url() === 'https://flow.test/invoke'
            && $body['event'] === 'mood.sick'
            && $body['category'] === 'Wellbeing'
            && $body['employee'] === 'Sam Cruz'
            && $body['display_name'] === 'Sam'
            && str_contains($body['text'], 'offline');
    });
});

it('does not post to Teams for other moods', function () {
    config()->set('services.teams.flow_url', 'https://flow.test/invoke');
    Http::fake();

    $this->actingAs(User::factory()->create(['status' => 'active']));

    foreach ([Mood::HAPPY, Mood::CALM, Mood::STRESSED, Mood::TIRED] as $mood) {
        Livewire::test(MoodCheckInWidget::class)->call('logMood', $mood->value);
    }

    Http::assertNothingSent();
});

it('does not post to Teams twice for the same day', function () {
    config()->set('services.teams.flow_url', 'https://flow.test/invoke');
    Http::fake();

    $this->actingAs(User::factory()->create(['status' => 'active']));

    Livewire::test(MoodCheckInWidget::class)
        ->call('logMood', Mood::SICK->value)
        ->call('logMood', Mood::SICK->value);

    Http::assertSentCount(1);
});

it('stays silent when no Teams flow url is configured', function () {
    config()->set('services.teams.flow_url', null);
    Http::fake();

    $this->actingAs(User::factory()->create(['status' => 'active']));

    Livewire::test(MoodCheckInWidget::class)->call('logMood', Mood::SICK->value);

    Http::assertNothingSent();
});
