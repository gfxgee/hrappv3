<?php

use App\Http\Responses\FilamentLoginResponse;
use App\Models\User;
use App\Support\MobileAudience;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;

function requestAs(User $user, ?string $userAgent = null): Request
{
    $request = Request::create('/admin/login', 'POST', server: $userAgent ? ['HTTP_USER_AGENT' => $userAgent] : []);
    $request->setUserResolver(fn () => $user);

    return $request;
}

it('treats a regular employee as the mobile audience', function () {
    expect(MobileAudience::matches(requestAs(User::factory()->create())))->toBeTrue();
});

it('does not treat a desktop manager as the mobile audience', function () {
    Role::findOrCreate('hr', 'web');
    $manager = User::factory()->create();
    $manager->assignRole('hr');

    expect(MobileAudience::matches(requestAs($manager)))->toBeFalse();
});

it('treats a manager on a phone as the mobile audience', function () {
    Role::findOrCreate('hr', 'web');
    $manager = User::factory()->create();
    $manager->assignRole('hr');

    $iphone = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 Mobile/15E148 Safari/604.1';

    expect(MobileAudience::matches(requestAs($manager, $iphone)))->toBeTrue();
});

it('redirects the mobile audience to the mobile app after Filament login', function () {
    $response = (new FilamentLoginResponse)->toResponse(requestAs(User::factory()->create()));

    expect($response)->toBeInstanceOf(RedirectResponse::class)
        ->and($response->getTargetUrl())->toBe(route('mobile.home'));
});
