<?php

use App\Http\Controllers\Api\BiometricPunchController;
use App\Http\Controllers\Api\TodaysLeavesController;
use App\Http\Controllers\Api\UpcomingController;
use App\Http\Middleware\VerifyBiometricWebhookSecret;
use Illuminate\Support\Facades\Route;

// Biometric attendance webhook — called by the SharePoint/Power Automate flow
// on each new punch. Stateless JSON, guarded by a shared-secret header.
Route::post('attendance/biometric-punch', BiometricPunchController::class)
    ->middleware(VerifyBiometricWebhookSecret::class)
    ->name('api.attendance.biometric-punch');

// Today's leaves — name and reason of everyone on leave today.
Route::get('leaves/today', TodaysLeavesController::class)
    ->name('api.leaves.today');

// Upcoming events within a look-ahead window (?days= overrides the default).
Route::get('upcoming/leaves', [UpcomingController::class, 'leaves'])->name('api.upcoming.leaves');
Route::get('upcoming/birthdays', [UpcomingController::class, 'birthdays'])->name('api.upcoming.birthdays');
Route::get('upcoming/holidays', [UpcomingController::class, 'holidays'])->name('api.upcoming.holidays');
