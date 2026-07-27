<?php

use Filament\Facades\Filament;

it('excludes the mobile app surface from SPA navigation', function () {
    $exceptions = Filament::getPanel('admin')->getSpaUrlExceptions();

    // The Inertia mobile app can't boot via wire:navigate, so the whole /m
    // surface must full-page-load (this is what keeps mobile login working).
    expect($exceptions)->toContain('*/m')
        ->and($exceptions)->toContain('*/m/*');
});
