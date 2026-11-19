<?php

use App\Support\TeamsIconValidator;
use Illuminate\Support\Str;

/**
 * Teams enforces exact icon dimensions and rejects the upload otherwise, so a
 * replacement icon of the wrong size should fail at build time rather than
 * after a round trip through the Teams admin centre.
 */
beforeEach(function () {
    $this->icons = storage_path('framework/testing/icons-'.Str::random(8));
    mkdir($this->icons, 0775, true);
});

afterEach(function () {
    array_map('unlink', glob($this->icons.'/*') ?: []);
    rmdir($this->icons);
});

function writePng(string $path, int $width, int $height): void
{
    $image = imagecreatetruecolor($width, $height);
    imagepng($image, $path);
}

it('accepts correctly sized icons', function () {
    writePng($this->icons.'/color.png', 192, 192);
    writePng($this->icons.'/outline.png', 32, 32);

    expect((new TeamsIconValidator)->violations($this->icons))->toBe([]);
});

it('accepts the icons the app actually ships', function () {
    expect((new TeamsIconValidator)->violations(resource_path('teams')))->toBe([]);
});

it('rejects a colour icon that is not 192x192', function () {
    writePng($this->icons.'/color.png', 512, 512);
    writePng($this->icons.'/outline.png', 32, 32);

    $violations = (new TeamsIconValidator)->violations($this->icons);

    expect($violations)->toHaveCount(1)
        ->and($violations[0])->toContain('192x192')
        ->and($violations[0])->toContain('found 512x512');
});

it('rejects an icon that is not square', function () {
    writePng($this->icons.'/color.png', 192, 150);
    writePng($this->icons.'/outline.png', 32, 32);

    expect((new TeamsIconValidator)->violations($this->icons))->toHaveCount(1);
});

it('rejects an outline icon that is not 32x32', function () {
    writePng($this->icons.'/color.png', 192, 192);
    writePng($this->icons.'/outline.png', 64, 64);

    expect((new TeamsIconValidator)->violations($this->icons))->toHaveCount(1);
});

it('rejects a file that is not a PNG', function () {
    $image = imagecreatetruecolor(192, 192);
    imagejpeg($image, $this->icons.'/color.png');
    writePng($this->icons.'/outline.png', 32, 32);

    $violations = (new TeamsIconValidator)->violations($this->icons);

    expect($violations)->toHaveCount(1)
        ->and($violations[0])->toContain('must be a PNG');
});

it('reports a missing icon', function () {
    writePng($this->icons.'/color.png', 192, 192);

    $violations = (new TeamsIconValidator)->violations($this->icons);

    expect($violations)->toHaveCount(1)
        ->and($violations[0])->toContain('outline.png');
});
