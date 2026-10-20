<?php

use App\Http\Controllers\Api\MobileAppointmentController;
use App\Http\Controllers\Api\MobileAuthController;
use App\Http\Controllers\Api\MobileBillingController;
use App\Http\Controllers\Api\MobileMedicalRecordController;
use App\Http\Controllers\Api\MobilePatientController;
use App\Http\Controllers\Api\MobileTeleconsultationController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::post('/auth/login', [MobileAuthController::class, 'login'])
        ->middleware('throttle:6,1')
        ->name('auth.login');

    Route::middleware(['auth:sanctum', 'mobile-account'])->group(function (): void {
        Route::post('/auth/logout', [MobileAuthController::class, 'logout'])->name('auth.logout');
        Route::get('/me', [MobilePatientController::class, 'show'])->name('me');
        Route::get('/patients', [MobilePatientController::class, 'patients'])->name('patients.index');
        Route::get('/patients/{patient}/medical-records', [MobileMedicalRecordController::class, 'index'])
            ->whereNumber('patient')
            ->name('patients.medical-records.index');
        Route::get('/bills', [MobileBillingController::class, 'index'])->name('bills.index');
        Route::post('/bills/{bill}/payment-submissions', [MobileBillingController::class, 'submitPayment'])
            ->whereNumber('bill')
            ->name('bills.payment-submissions.store');
        Route::get('/schedules', [MobileAppointmentController::class, 'schedules'])->name('schedules.index');
        Route::get('/appointments', [MobileAppointmentController::class, 'index'])->name('appointments.index');
        Route::post('/appointments', [MobileAppointmentController::class, 'store'])->name('appointments.store');
        Route::get('/teleconsultations', [MobileTeleconsultationController::class, 'index'])->name('teleconsultations.index');
        Route::get('/teleconsultations/{appointment}/messages', [MobileTeleconsultationController::class, 'messages'])
            ->whereNumber('appointment')
            ->name('teleconsultations.messages.index');
        Route::post('/teleconsultations/{appointment}/messages', [MobileTeleconsultationController::class, 'sendMessage'])
            ->whereNumber('appointment')
            ->name('teleconsultations.messages.store');
    });
});
