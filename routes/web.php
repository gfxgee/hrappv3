<?php

use App\Http\Controllers\Auth\MicrosoftSsoController;
use App\Http\Controllers\DtrPdfController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

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
    Route::inertia('dashboard', 'dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';
