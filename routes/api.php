<?php

use App\Http\Controllers\Api\BiometricPunchController;
use App\Http\Controllers\Api\OnCallController;
use App\Http\Controllers\Api\TodaysLeavesController;
use App\Http\Controllers\Api\TodaysWfhController;
use App\Http\Controllers\Api\UpcomingController;
use App\Http\Middleware\VerifyBiometricWebhookSecret;
use Illuminate\Support\Facades\Route;

// Biometric attendance webhook — called by the SharePoint/Power Automate flow
// on each new punch. Stateless JSON, guarded by a shared-secret header.
Route::post('attendance/biometric-punch', BiometricPunchController::class)
    ->middleware(VerifyBiometricWebhookSecret::class)
    ->name('api.attendance.biometric-punch');

// Today's leaves — name and reason of everyone on leave today. Accepts
// ?date=YYYY-MM-DD to inspect any date (e.g. to see the payload on a weekday).
Route::get('leaves/today', TodaysLeavesController::class)
    ->name('api.leaves.today');

// Today's WFH — everyone working from home today (same ?date= override).
Route::get('leaves/wfh', TodaysWfhController::class)
    ->name('api.leaves.wfh');

// On-call ("late dev"): `current` is the week's owner; `today` is who is
// effectively covering today (owner, or a stand-in on the owner's leave days).
// Both accept ?date=YYYY-MM-DD.
Route::get('on-call/current', [OnCallController::class, 'current'])
    ->name('api.on-call.current');
Route::get('on-call/today', [OnCallController::class, 'today'])
    ->name('api.on-call.today');

// Upcoming events within a look-ahead window (?days= overrides the default).
Route::get('upcoming/leaves', [UpcomingController::class, 'leaves'])->name('api.upcoming.leaves');
Route::get('upcoming/birthdays', [UpcomingController::class, 'birthdays'])->name('api.upcoming.birthdays');
Route::get('upcoming/anniversaries', [UpcomingController::class, 'anniversaries'])->name('api.upcoming.anniversaries');
Route::get('upcoming/holidays', [UpcomingController::class, 'holidays'])->name('api.upcoming.holidays');
