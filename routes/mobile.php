<?php

use App\Http\Controllers\Mobile\AlertController;
use App\Http\Controllers\Mobile\AttendanceController;
use App\Http\Controllers\Mobile\HomeController;
use App\Http\Controllers\Mobile\LeaveController;
use App\Http\Controllers\Mobile\OvertimeController;
use App\Http\Controllers\Mobile\PunchController;
use App\Http\Controllers\Mobile\TeamController;
use App\Http\Middleware\ShareMobileBadge;
use Illuminate\Support\Facades\Route;

/*
| The employee self-service mobile app. Regular employees are routed here
| (see RedirectToMobileApp); HR/admins keep the Filament admin panel. Deep HR
| work stays on desktop — this is the clock-in / leave / attendance surface.
*/
Route::middleware(['auth', 'verified', ShareMobileBadge::class])
    ->prefix('m')
    ->name('mobile.')
    ->group(function () {
        Route::redirect('/', '/m/home');

        Route::get('home', [HomeController::class, 'index'])->name('home');
        Route::post('punch', [PunchController::class, 'store'])->name('punch');

        Route::get('attendance', [AttendanceController::class, 'index'])->name('attendance');
        Route::post('attendance/correction', [AttendanceController::class, 'storeCorrection'])->name('attendance.correction');

        Route::post('leave', [LeaveController::class, 'store'])->name('leave.store');
        Route::post('overtime', [OvertimeController::class, 'store'])->name('overtime.store');

        Route::get('team', [TeamController::class, 'index'])->name('team');

        Route::get('alerts', [AlertController::class, 'index'])->name('alerts');
        Route::post('alerts/read', [AlertController::class, 'markAllRead'])->name('alerts.read');
    });
