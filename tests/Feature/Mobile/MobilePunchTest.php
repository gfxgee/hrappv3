<?php

use App\Models\AttendanceLog;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('renders the mobile home with clock, balances, and recent requests', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('mobile.home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('mobile/home')
            ->has('clock')
            ->has('balances')
            ->has('recent')
        );
});

it('records a mobile clock-in on the first punch', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->post(route('mobile.punch'));

    $log = AttendanceLog::query()->where('user_id', $user->id)->sole();

    expect($log->type)->toBe('clockin')
        ->and($log->device)->toBe('mobile');
});

it('clocks out on the second punch of the day', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->post(route('mobile.punch')); // in
    $this->post(route('mobile.punch')); // out

    expect(AttendanceLog::where('user_id', $user->id)->where('type', 'clockin')->count())->toBe(1)
        ->and(AttendanceLog::where('user_id', $user->id)->where('type', 'clockout')->count())->toBe(1);
});

it('does not start a second shift once the day is complete', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->post(route('mobile.punch')); // in
    $this->post(route('mobile.punch')); // out
    $this->post(route('mobile.punch')); // no-op — shift already done

    expect(AttendanceLog::where('user_id', $user->id)->count())->toBe(2);
});

it('requires authentication to punch', function () {
    $this->post(route('mobile.punch'))->assertRedirect(route('login'));
});
