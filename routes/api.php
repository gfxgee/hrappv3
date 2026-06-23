<?php

use App\Http\Controllers\Api\BiometricPunchController;
use App\Http\Middleware\VerifyBiometricWebhookSecret;
use Illuminate\Support\Facades\Route;

// Biometric attendance webhook — called by the SharePoint/Power Automate flow
// on each new punch. Stateless JSON, guarded by a shared-secret header.
Route::post('attendance/biometric-punch', BiometricPunchController::class)
    ->middleware(VerifyBiometricWebhookSecret::class)
    ->name('api.attendance.biometric-punch');
