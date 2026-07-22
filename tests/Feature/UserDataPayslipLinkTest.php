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

it('hides the payslip link from array and JSON serialization', function () {
    $user = User::factory()->create();
    $user->userData()->create(['payslip_link' => 'https://docs.google.com/spreadsheets/d/1AbcXyz/edit']);

    $userData = $user->refresh()->userData;

    // Not exposed when the model is serialized for the frontend...
    expect($userData->toArray())->not->toHaveKey('payslip_link')
        ->and(json_decode($userData->toJson(), true))->not->toHaveKey('payslip_link')
        ->and($user->load('userData')->toArray()['user_data'] ?? [])->not->toHaveKey('payslip_link');

    // ...but still readable via direct attribute access where authorized.
    expect($userData->payslip_link)->not->toBeNull();
});
