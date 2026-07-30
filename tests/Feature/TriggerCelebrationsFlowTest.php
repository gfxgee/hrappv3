<?php

use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('services.teams.celebrations_flow_url', 'https://flow.test/celebrations');
    Http::fake();
});

it('triggers the flow with today\'s birthdays and anniversaries', function () {
    User::factory()->create(['name' => 'Birthday Person', 'birthday' => today()->format('1990-m-d')]);
    User::factory()->create(['name' => 'Anniversary Person', 'date_hired' => today()->format('2020-m-d')]);

    $this->artisan('celebrations:trigger-flow')->assertSuccessful();

    Http::assertSent(function (Request $request): bool {
        $body = $request->data();

        return $request->url() === 'https://flow.test/celebrations'
            && $body['event'] === 'celebrations.today'
            && $body['birthday_count'] === 1
            && $body['anniversary_count'] === 1
            && $body['birthdays'][0]['name'] === 'Birthday Person'
            && $body['anniversaries'][0]['name'] === 'Anniversary Person'
            && $body['anniversaries'][0]['years'] === today()->year - 2020;
    });
});

it('does not trigger the flow when nobody is celebrating', function () {
    User::factory()->create([
        'birthday' => today()->addDays(10)->format('1990-m-d'),
        'date_hired' => today()->addDays(10)->format('2020-m-d'),
    ]);

    $this->artisan('celebrations:trigger-flow')
        ->expectsOutputToContain('No celebrations today')
        ->assertSuccessful();

    Http::assertNothingSent();
});

it('does nothing when the flow url is not configured', function () {
    config()->set('services.teams.celebrations_flow_url', null);
    User::factory()->create(['name' => 'Birthday Person', 'birthday' => today()->format('1990-m-d')]);

    $this->artisan('celebrations:trigger-flow')
        ->expectsOutputToContain('not set')
        ->assertSuccessful();

    Http::assertNothingSent();
});

it('excludes employees hired today from anniversaries', function () {
    User::factory()->create(['name' => 'Hired Today', 'date_hired' => today()->format('Y-m-d')]);

    $this->artisan('celebrations:trigger-flow')->assertSuccessful();

    Http::assertNothingSent();
});

it('can be forced to send on a day with no celebrations', function () {
    $this->artisan('celebrations:trigger-flow --force')->assertSuccessful();

    Http::assertSent(fn (Request $request): bool => $request->data()['birthday_count'] === 0
        && $request->data()['anniversary_count'] === 0);
});
