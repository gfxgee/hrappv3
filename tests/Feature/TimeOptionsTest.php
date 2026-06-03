<?php

use App\Support\TimeOptions;

it('generates quarter-hour options from 00:00 through 24:00', function () {
    $options = TimeOptions::quarterHours();

    // 24 hours * 4 quarter-hours per hour + the final 24:00 entry
    expect($options)->toHaveCount(97);

    expect(array_key_first($options))->toBe('00:00')
        ->and(array_key_last($options))->toBe('24:00');

    expect($options['10:00'])->toBe('10:00')
        ->and($options['10:15'])->toBe('10:15')
        ->and($options['18:00'])->toBe('18:00')
        ->and($options['23:45'])->toBe('23:45');
});

it('omits invalid times like 24:15', function () {
    $options = TimeOptions::quarterHours();

    expect($options)
        ->not->toHaveKey('24:15')
        ->and($options)->not->toHaveKey('25:00');
});
