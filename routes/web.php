<?php

use App\Http\Controllers\Auth\MicrosoftSsoController;
use App\Http\Controllers\DtrPdfController;
use App\Http\Controllers\IclockController;
use App\Http\Controllers\LandingController;
use App\Http\Middleware\RedirectToMobileApp;
use Illuminate\Support\Facades\Route;

Route::get('/', LandingController::class)->name('home');

// ZKTeco biometric scanner endpoints (no auth — the device speaks plain HTTP
// over a static IP and cannot log in). Scans are stored raw and synced into
// attendance_logs. The public status page is a non-sensitive heartbeat; the
// detail page is gated to HR/admins inside the controller.
Route::get('iclock/cdata', [IclockController::class, 'handshake']);
Route::post('iclock/cdata', [IclockController::class, 'receiveRecords']);
Route::get('iclock/getrequest', [IclockController::class, 'getrequest']);
Route::get('iclock/status', [IclockController::class, 'status'])->name('iclock.status');
Route::get('iclock/status/detail', [IclockController::class, 'statusDetail'])
    ->middleware('auth')
    ->name('iclock.status.detail');

// Printable Daily Time Record PDF (authorization handled in the controller).
Route::get('dtr/pdf', DtrPdfController::class)
    ->middleware('auth')
    ->name('dtr.pdf');

// Microsoft / Azure AD SSO for the admin panel login.
Route::get('admin/auth/microsoft/redirect', [MicrosoftSsoController::class, 'redirect'])
    ->name('sso.microsoft.redirect');
Route::get('admin/auth/microsoft/callback', [MicrosoftSsoController::class, 'callback'])
    ->name('sso.microsoft.callback');

Route::middleware(['auth', 'verified'])->group(function () {
    // Regular employees are bounced to the mobile app; HR/admins and team
    // leaders keep the desktop dashboard.
    Route::inertia('dashboard', 'dashboard')
        ->middleware(RedirectToMobileApp::class)
        ->name('dashboard');
});

require __DIR__.'/mobile.php';
require __DIR__.'/settings.php';
