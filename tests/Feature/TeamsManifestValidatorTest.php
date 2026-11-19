<?php

use App\Support\TeamsManifestValidator;

/**
 * Teams validates the manifest only when somebody uploads the package, and
 * surfaces one violation at a time — so a mistake here is paid for in
 * build-upload-read-error round trips. These pin the checks that catch it
 * first.
 */
function validManifest(): array
{
    return [
        'manifestVersion' => '1.17',
        'version' => '1.0.0',
        'id' => 'a8432502-1b8f-4e48-80cc-acdbad75236f',
        'developer' => [
            'name' => 'Digital Feet',
            'websiteUrl' => 'https://hrapp.example.com',
            'privacyUrl' => 'https://hrapp.example.com/privacy',
            'termsOfUseUrl' => 'https://hrapp.example.com/terms',
        ],
        'icons' => ['color' => 'color.png', 'outline' => 'outline.png'],
        'name' => ['short' => 'HR App', 'full' => 'HR App'],
        'description' => ['short' => 'File leave.', 'full' => 'File leave and overtime.'],
        'accentColor' => '#592BD7',
    ];
}

it('accepts a well-formed manifest', function () {
    expect((new TeamsManifestValidator)->violations(validManifest()))->toBe([]);
});

it('accepts the manifest template the app actually ships', function () {
    $rendered = str_replace(
        ['{{APP_ID}}', '{{APP_URL}}', '{{APP_HOST}}', '{{APP_NAME}}', '{{CLIENT_ID}}'],
        [
            'a8432502-1b8f-4e48-80cc-acdbad75236f',
            'https://hrapp.example.com',
            'hrapp.example.com',
            'HR App',
            'e0030282-63db-43e6-9156-28ab24e20634',
        ],
        (string) file_get_contents(resource_path('teams/manifest.json')),
    );

    expect((new TeamsManifestValidator)->violations(json_decode($rendered, true)))->toBe([]);
});

it('rejects a property the schema does not define', function () {
    // "packageName" was valid in older manifest versions and dropped by 1.17.
    // Teams refuses the whole upload over it.
    $violations = (new TeamsManifestValidator)->violations(
        [...validManifest(), 'packageName' => 'com.example.app'],
    );

    expect($violations)->toHaveCount(1)
        ->and($violations[0])->toContain('packageName');
});

it('reports a missing required property', function () {
    $manifest = validManifest();
    unset($manifest['accentColor']);

    expect((new TeamsManifestValidator)->violations($manifest))
        ->toHaveCount(1)
        ->and((new TeamsManifestValidator)->violations($manifest)[0])
        ->toContain('accentColor');
});

it('rejects an accent colour that is not a six-digit hex', function () {
    expect((new TeamsManifestValidator)->violations([...validManifest(), 'accentColor' => 'purple']))
        ->toHaveCount(1);
});

it('accepts a valid accent colour despite the hash in the schema pattern', function () {
    // The schema's pattern contains "#", which breaks a naive regex delimiter
    // and would report every valid colour as invalid.
    expect((new TeamsManifestValidator)->violations([...validManifest(), 'accentColor' => '#FFAA00']))
        ->toBe([]);
});

it('rejects a short name that is too long for the app bar', function () {
    $manifest = validManifest();
    $manifest['name']['short'] = str_repeat('a', 100);

    expect((new TeamsManifestValidator)->violations($manifest))->not->toBeEmpty();
});

it('validates inside nested objects and arrays', function () {
    $manifest = validManifest();
    $manifest['staticTabs'] = [[
        'entityId' => 'hrapp.home',
        'name' => 'HR',
        'contentUrl' => 'https://hrapp.example.com/teams',
        'scopes' => ['personal'],
        'notAThing' => true,
    ]];

    $violations = (new TeamsManifestValidator)->violations($manifest);

    expect($violations)->toHaveCount(1)
        ->and($violations[0])->toContain('staticTabs[0]')
        ->and($violations[0])->toContain('notAThing');
});
