<?php

use App\Models\User;

/**
 * Filament's non-native select always opens its list at the top. On the
 * quarter-hour time pickers that means a field holding 18:00 opens showing
 * 00:00, and you scroll past seventy-two options to reach where you already
 * are.
 *
 * resources/js/filament-select-scroll.ts centres the selected option on open.
 * The behaviour itself is a browser thing; what these tests hold is that the
 * script is actually loaded on both panels, and that it is still aimed at the
 * class names Filament renders — a rename upstream would break it in silence.
 */
it('loads the script on both panels, because the lists are long in both', function () {
    $teams = file_get_contents(app_path('Providers/Filament/TeamsPanelProvider.php'));
    $admin = file_get_contents(app_path('Providers/Filament/AdminPanelProvider.php'));

    expect($teams)->toContain('filament.shared.select-scroll')
        ->and($admin)->toContain('filament.shared.select-scroll');

    $vite = file_get_contents(base_path('vite.config.ts'));

    expect($vite)->toContain('resources/js/filament-select-scroll.ts');
});

it('targets the class names Filament actually renders', function () {
    $script = file_get_contents(resource_path('js/filament-select-scroll.ts'));
    $vendor = file_get_contents(base_path('vendor/filament/forms/dist/components/select.js'));
    $theme = file_get_contents(base_path('vendor/filament/filament/dist/theme.css'));

    // Both sides of the coupling: what the script looks for, and what
    // Filament still ships.
    foreach (['fi-select-input-btn', 'fi-select-input-ctn', 'fi-select-input-option'] as $class) {
        expect($script)->toContain($class)
            ->and($vendor.$theme)->toContain($class);
    }

    // The panel and the selected-option marker come from the same markup.
    expect($script)->toContain('fi-dropdown-panel')
        ->and($script)->toContain('fi-selected')
        ->and($theme)->toContain('fi-dropdown-panel');
});

it('serves the script to a signed-in employee in the tab', function () {
    $user = User::factory()->create(['status' => 'active']);
    $user->userData()->create(['time_in' => '09:00', 'time_out' => '18:00']);

    // The leave form is the page with the time pickers on it.
    $this->actingAs($user->fresh())
        ->get('/teams-app/file-leave-request')
        ->assertOk()
        ->assertSee('filament-select-scroll', escape: false);
});
