<?php

use App\Http\Responses\FilamentLoginResponse;
use App\Support\MobileAudience;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

function requestWithAgent(?string $userAgent): Request
{
    return Request::create('/admin/login', 'POST', server: $userAgent ? ['HTTP_USER_AGENT' => $userAgent] : []);
}

it('detects a phone as the mobile audience', function () {
    expect(MobileAudience::isMobile(requestWithAgent(iphoneUa())))->toBeTrue();
});

it('does not treat a desktop browser as mobile', function () {
    $desktop = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Safari/537.36';

    expect(MobileAudience::isMobile(requestWithAgent($desktop)))->toBeFalse();
});

it('redirects a phone to the mobile app after Filament login', function () {
    $response = (new FilamentLoginResponse)->toResponse(requestWithAgent(iphoneUa()));

    expect($response)->toBeInstanceOf(RedirectResponse::class)
        ->and($response->getTargetUrl())->toBe(route('mobile.home'));
});
