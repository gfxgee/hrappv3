<?php

use App\Services\ReasonEnhancer;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Testing\TextResponseFake;

it('enhances a leave reason using the configured provider', function () {
    Prism::fake([
        TextResponseFake::make()->withText('I am requesting sick leave as I am feeling unwell.'),
    ]);

    $result = app(ReasonEnhancer::class)->enhance('sick cant come', 'polish', ['kind' => 'leave']);

    expect($result)->toBe('I am requesting sick leave as I am feeling unwell.');
});

it('trims whitespace from the AI response', function () {
    Prism::fake([
        TextResponseFake::make()->withText('  Padded reason.  '),
    ]);

    expect(app(ReasonEnhancer::class)->enhance('x', 'expand'))->toBe('Padded reason.');
});

it('detects whether the provider api key is configured', function () {
    config()->set('ai.enhance.provider', 'gemini');

    config()->set('prism.providers.gemini.api_key', '');
    expect(app(ReasonEnhancer::class)->isConfigured())->toBeFalse();

    config()->set('prism.providers.gemini.api_key', 'test-key-123');
    expect(app(ReasonEnhancer::class)->isConfigured())->toBeTrue();
});

it('passes leave-type context into the prompt for leave kind', function () {
    $fake = Prism::fake([
        TextResponseFake::make()->withText('Polished sick reason.'),
    ]);

    app(ReasonEnhancer::class)->enhance('stomach hurts', 'polish', [
        'kind' => 'leave',
        'leave_type' => 'Sick Leave',
    ]);

    $fake->assertRequest(function (array $requests): void {
        $systemPrompt = $requests[0]->systemPrompts()[0]->content;

        expect($systemPrompt)
            ->toContain('leave request')
            ->toContain('Sick Leave');
    });
});

it('uses an overtime-specific prompt for overtime kind', function () {
    $fake = Prism::fake([
        TextResponseFake::make()->withText('Polished overtime reason.'),
    ]);

    app(ReasonEnhancer::class)->enhance('finishing deploy', 'polish', [
        'kind' => 'overtime',
        'hours' => '2.5',
    ]);

    $fake->assertRequest(function (array $requests): void {
        $systemPrompt = $requests[0]->systemPrompts()[0]->content;

        expect($systemPrompt)
            ->toContain('overtime request')
            ->toContain('2.5')
            ->not->toContain('leave request');
    });
});

it('uses a recognition prompt for the praise kind', function () {
    $fake = Prism::fake([
        TextResponseFake::make()->withText('You consistently lift the whole team.'),
    ]);

    app(ReasonEnhancer::class)->enhance('helps everyone', 'polish', ['kind' => 'praise']);

    $fake->assertRequest(function (array $requests): void {
        $systemPrompt = $requests[0]->systemPrompts()[0]->content;

        expect($systemPrompt)
            ->toContain('recognition')
            ->not->toContain('leave request');
    });
});

it('uses a comment prompt for the comment kind', function () {
    $fake = Prism::fake([
        TextResponseFake::make()->withText('Totally agree, well deserved!'),
    ]);

    app(ReasonEnhancer::class)->enhance('yes', 'polish', ['kind' => 'comment']);

    $fake->assertRequest(function (array $requests): void {
        $systemPrompt = $requests[0]->systemPrompts()[0]->content;

        expect($systemPrompt)
            ->toContain('comment')
            ->not->toContain('leave request');
    });
});
