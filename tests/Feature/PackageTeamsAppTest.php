<?php

use Illuminate\Support\Str;

/**
 * The Teams app package. Teams rejects a package whose manifest does not match
 * the app registration or is served over http, and it does so with messages
 * that do not say which it was — so the command refuses to build one instead.
 */
beforeEach(function () {
    $this->output = storage_path('framework/testing/teams-'.Str::random(8));

    config([
        'app.url' => 'https://hrapp.example.com',
        'app.name' => 'HR App',
        'services.microsoft.client_id' => '11111111-1111-1111-1111-111111111111',
        'services.teams.app_id' => '22222222-2222-2222-2222-222222222222',
    ]);
});

afterEach(function () {
    if (is_dir($this->output)) {
        array_map('unlink', glob($this->output.'/*') ?: []);
        rmdir($this->output);
    }
});

function packageContents(string $output): array
{
    $zip = new ZipArchive;
    $zip->open($output.'/hr-app-teams.zip');

    $files = [];

    for ($i = 0; $i < $zip->numFiles; $i++) {
        $files[$zip->getNameIndex($i)] = $zip->getFromIndex($i);
    }

    $zip->close();

    return $files;
}

it('builds a package containing the manifest and both icons', function () {
    $this->artisan('teams:package', ['--output' => $this->output])->assertSuccessful();

    $files = packageContents($this->output);

    // Teams looks for exactly these names at the root of the archive.
    expect($files)->toHaveKeys(['manifest.json', 'color.png', 'outline.png'])
        ->and($files['color.png'])->not->toBeEmpty()
        ->and($files['outline.png'])->not->toBeEmpty();
});

it('fills the manifest in with the live host and application ids', function () {
    $this->artisan('teams:package', ['--output' => $this->output])->assertSuccessful();

    $manifest = json_decode(packageContents($this->output)['manifest.json'], true);

    expect($manifest['id'])->toBe('22222222-2222-2222-2222-222222222222')
        ->and($manifest['staticTabs'][0]['contentUrl'])->toBe('https://hrapp.example.com/teams')
        ->and($manifest['validDomains'])->toBe(['hrapp.example.com'])
        // Entra will only issue a token for the resource named here, so it has
        // to match the Application ID URI on the registration exactly.
        ->and($manifest['webApplicationInfo']['id'])->toBe('11111111-1111-1111-1111-111111111111')
        ->and($manifest['webApplicationInfo']['resource'])
        ->toBe('api://hrapp.example.com/11111111-1111-1111-1111-111111111111')
        ->and($manifest)->not->toContain('{{');
});

it('refuses to build against a plain http address', function () {
    config(['app.url' => 'http://hrapp.test']);

    $this->artisan('teams:package', ['--output' => $this->output])->assertFailed();

    expect(glob($this->output.'/*') ?: [])->toBeEmpty();
});

it('refuses to build without the Microsoft client id', function () {
    config(['services.microsoft.client_id' => '']);

    $this->artisan('teams:package', ['--output' => $this->output])->assertFailed();
});

it('refuses to build without a Teams app id and suggests one', function () {
    config(['services.teams.app_id' => null]);

    $this->artisan('teams:package', ['--output' => $this->output])
        ->expectsOutputToContain('TEAMS_APP_ID=')
        ->assertFailed();
});
