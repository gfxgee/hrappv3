<?php

use App\Models\User;

it('stores a payslip link on user data', function () {
    $user = User::factory()->create();
    $link = 'https://docs.google.com/spreadsheets/d/1AbcXyz/edit';

    $user->userData()->create(['payslip_link' => $link]);

    expect($user->refresh()->userData->payslip_link)->toBe($link);

    $this->assertDatabaseHas('user_data', [
        'user_id' => $user->id,
        'payslip_link' => $link,
    ]);
});

it('does not leak the payslip link to the mobile frontend', function () {
    $user = User::factory()->create();
    $user->userData()->create(['payslip_link' => 'https://docs.google.com/spreadsheets/d/SECRET123/edit']);

    // The mobile Account screen carries the user's own profile, but must not
    // expose the payslip link in its Inertia props.
    $this->actingAs($user)
        ->get(route('mobile.account'))
        ->assertOk()
        ->assertDontSee('payslip_link')
        ->assertDontSee('SECRET123');
});
