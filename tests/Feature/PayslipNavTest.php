<?php

use App\Models\User;
use Filament\Facades\Filament;

beforeEach(function () {
    Filament::setCurrentPanel('admin');
});

it('shows the payslip link in the panel nav when the user has one', function () {
    $link = 'https://docs.google.com/spreadsheets/d/1AbcXyz/edit';
    $user = User::factory()->create();
    $user->userData()->create(['payslip_link' => $link]);

    $this->actingAs($user)
        ->get('/admin')
        ->assertOk()
        ->assertSee($link);
});

it('does not show the payslip nav item when the user has no link', function () {
    $user = User::factory()->create();
    $user->userData()->create(['payslip_link' => null]);

    $this->actingAs($user)
        ->get('/admin')
        ->assertOk()
        ->assertDontSee('>Payslip<', escape: false);
});
