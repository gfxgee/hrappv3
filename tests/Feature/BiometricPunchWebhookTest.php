<?php

use App\Models\AttendanceLog;
use App\Models\User;

const WEBHOOK_URL = '/api/attendance/biometric-punch';
const WEBHOOK_SECRET = 'test-webhook-secret';

beforeEach(function () {
    config()->set('services.biometric_webhook.secret', WEBHOOK_SECRET);
    // Pin the timezone so UTC test timestamps don't cross the local-day boundary
    // (the app runs in +8, where 17:00Z would fall on the next calendar day).
    config()->set('app.timezone', 'UTC');
});

/**
 * @param  array<string, mixed>  $payload
 */
function postPunch(array $payload, ?string $secret = WEBHOOK_SECRET)
{
    $headers = $secret === null ? [] : ['X-Webhook-Secret' => $secret];

    return test()->postJson(WEBHOOK_URL, $payload, $headers);
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function punchPayload(array $overrides = []): array
{
    return array_merge([
        'id' => 3029,
        'title' => 'TIME-IN',
        'email' => 'vevien@digitalfeet.com',
        'punched_at' => '2026-06-23T08:01:00Z',
    ], $overrides);
}

it('rejects a request without the secret', function () {
    postPunch(punchPayload(), secret: null)->assertUnauthorized();
    postPunch(punchPayload(), secret: 'wrong')->assertUnauthorized();

    expect(AttendanceLog::query()->count())->toBe(0);
});

it('validates the payload', function () {
    postPunch(['id' => 1])->assertStatus(422)
        ->assertJsonValidationErrors(['title', 'email', 'punched_at']);

    postPunch(punchPayload(['title' => 'LUNCH']))->assertStatus(422)
        ->assertJsonValidationErrors(['title']);
});

it('logs a TIME-IN as a clock-in matched by email', function () {
    $user = User::factory()->create(['email' => 'vevien@digitalfeet.com']);

    postPunch(punchPayload(['title' => 'TIME-IN']))
        ->assertOk()
        ->assertJson(['status' => 'created', 'type' => 'clockin']);

    $log = AttendanceLog::query()->firstOrFail();

    expect($log->user_id)->toBe($user->id)
        ->and($log->type)->toBe('clockin')
        ->and($log->device)->toBe('biometric')
        ->and($log->external_id)->toBe('sharepoint:3029');
});

it('logs a TIME-OUT as a clock-out', function () {
    User::factory()->create(['email' => 'vevien@digitalfeet.com']);

    postPunch(punchPayload(['id' => 3030, 'title' => 'TIME-OUT']))
        ->assertOk()
        ->assertJson(['status' => 'created', 'type' => 'clockout']);
});

it('matches email case-insensitively', function () {
    $user = User::factory()->create(['email' => 'vevien@digitalfeet.com']);

    postPunch(punchPayload(['email' => 'VeVieN@DigitalFeet.com']))->assertOk();

    expect(AttendanceLog::query()->where('user_id', $user->id)->count())->toBe(1);
});

it('resolves a SCAN to clock-in then clock-out across the day', function () {
    User::factory()->create(['email' => 'vevien@digitalfeet.com']);

    postPunch(punchPayload(['id' => 1, 'title' => 'SCAN', 'punched_at' => '2026-06-23T08:00:00Z']))
        ->assertJson(['type' => 'clockin']);

    postPunch(punchPayload(['id' => 2, 'title' => 'SCAN', 'punched_at' => '2026-06-23T17:00:00Z']))
        ->assertJson(['type' => 'clockout']);

    expect(AttendanceLog::query()->pluck('type')->all())->toBe(['clockin', 'clockout']);
});

it('is idempotent on re-delivery of the same punch', function () {
    User::factory()->create(['email' => 'vevien@digitalfeet.com']);

    postPunch(punchPayload(['id' => 3029]))->assertJson(['status' => 'created']);
    postPunch(punchPayload(['id' => 3029]))->assertJson(['status' => 'duplicate']);

    expect(AttendanceLog::query()->count())->toBe(1);
});

it('skips an unknown employee without erroring', function () {
    postPunch(punchPayload(['email' => 'ghost@digitalfeet.com']))
        ->assertOk()
        ->assertJson(['status' => 'unmatched']);

    expect(AttendanceLog::query()->count())->toBe(0);
});
