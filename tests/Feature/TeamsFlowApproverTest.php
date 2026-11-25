<?php

use App\Models\Department;
use App\Models\User;
use App\Services\TeamsNotifier;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

/**
 * Every payload sent to the main Power Automate flow must carry `approver`.
 *
 * TeamsNotifier::send() strips null fields, so a missing approver is not sent
 * as null — the key is absent from the JSON entirely, and the flow's Parse
 * JSON step fails on a required property. Nothing in Laravel errors: the
 * request goes out, the flow rejects it, and the only trace is a warning in
 * the log. These tests are the cheap way to notice.
 */
beforeEach(function () {
    config()->set('services.teams.flow_url', 'https://flow.test/invoke');
    config()->set('services.teams.default_approver', 'fallback@example.test');
});

function employeeUnder(?string $leaderEmail): User
{
    $department = Department::factory()->create();

    if ($leaderEmail !== null) {
        $leader = User::factory()->create(['email' => $leaderEmail, 'department_id' => $department->id]);
        $department->leaders()->syncWithoutDetaching([$leader->id]);
    }

    return User::factory()->create(['department_id' => $department->id]);
}

it('sends the approver on a sick check-in', function () {
    Http::fake();

    app(TeamsNotifier::class)->moodCheckedInSick(employeeUnder('leader@example.test'));

    Http::assertSent(fn ($request): bool => $request['event'] === 'mood.sick'
        && $request['approver'] === 'leader@example.test');
});

it('sends the approver on both on-call announcements', function () {
    Http::fake();

    $employee = employeeUnder('leader@example.test');
    $notifier = app(TeamsNotifier::class);

    $notifier->onCallAssigned($employee, Carbon::parse('2026-11-16'));
    $notifier->onCallStandIn($employee, null, Carbon::parse('2026-11-18'));

    foreach (['on_call.assigned', 'on_call.standin'] as $event) {
        Http::assertSent(fn ($request): bool => $request['event'] === $event
            && $request['approver'] === 'leader@example.test');
    }
});

it('falls back to the configured approver when the department has no leader', function () {
    Http::fake();

    app(TeamsNotifier::class)->moodCheckedInSick(employeeUnder(null));

    Http::assertSent(fn ($request): bool => $request['approver'] === 'fallback@example.test');
});

it('leaves no main-flow payload without an approver', function () {
    // The guard that catches the next one. Celebrations are excluded: they go
    // to their own flow URL, with its own schema and no approval in it.
    $source = file_get_contents(app_path('Services/TeamsNotifier.php'));

    preg_match_all("/'event' => '([a-z_.]+)'/", $source, $events);

    $mainFlowEvents = array_values(array_diff($events[1], ['celebrations.today']));

    expect($mainFlowEvents)->not->toBeEmpty();

    $missing = [];

    foreach ($mainFlowEvents as $event) {
        // The payload runs from its event line to the closing of the send call.
        $start = strpos($source, "'event' => '{$event}'");
        $end = strpos($source, '])', $start);

        if (! str_contains(substr($source, $start, $end - $start), 'approver')) {
            $missing[] = $event;
        }
    }

    // Named, so a failure says which payload rather than just "false".
    expect($missing)->toBe([]);
});
