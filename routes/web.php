<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\TeleconsultationController;
use App\Http\Controllers\Staff\DoctorManagementController;
use App\Http\Controllers\Staff\DoctorWorkflowController;
use App\Http\Controllers\StaffDashboardController;
use App\Http\Controllers\Staff\ReceptionController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('staff.dashboard');
})->name('home');

Route::redirect('/home-page', '/staff');
Route::redirect('/jadwal', '/staff');
Route::redirect('/pantau', '/staff');
Route::redirect('/konsultasi', '/patient/login');
Route::redirect('/registrasi', '/login');

Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.attempt');
Route::get('/patient/login', [AuthController::class, 'showPatientLogin'])->name('patient.login');
Route::post('/patient/login', [AuthController::class, 'patientLogin'])->name('patient.login.attempt');
Route::post('/logout', [AuthController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');

Route::middleware(['auth', 'patient'])
    ->prefix('patient')
    ->name('patient.')
    ->group(function (): void {
        Route::get('/teleconsultations', [TeleconsultationController::class, 'index'])->name('teleconsultations.index');
        Route::get('/teleconsultations/{appointment}', [TeleconsultationController::class, 'show'])->name('teleconsultations.show');
        Route::post('/teleconsultations/{appointment}/messages', [TeleconsultationController::class, 'sendMessage'])->name('teleconsultations.messages.store');
    });

Route::middleware(['auth', 'staff'])
    ->prefix('staff')
    ->name('staff.')
    ->group(function (): void {
        Route::get('/', StaffDashboardController::class)->name('dashboard');

        Route::middleware('staff-role:Dokter')
            ->prefix('doctor')
            ->name('doctor.')
            ->group(function (): void {
                Route::get('/appointments', [DoctorWorkflowController::class, 'index'])->name('appointments.index');
                Route::get('/appointments/{appointment}', [DoctorWorkflowController::class, 'show'])->name('appointments.show');
                Route::post('/appointments/{appointment}/consultation', [DoctorWorkflowController::class, 'storeConsultation'])->name('appointments.consultation.store');
                Route::get('/teleconsultations', [TeleconsultationController::class, 'index'])->name('teleconsultations.index');
                Route::get('/teleconsultations/{appointment}', [TeleconsultationController::class, 'show'])->name('teleconsultations.show');
                Route::post('/teleconsultations/{appointment}/messages', [TeleconsultationController::class, 'sendMessage'])->name('teleconsultations.messages.store');
                Route::post('/teleconsultations/{appointment}/complete', [TeleconsultationController::class, 'complete'])->name('teleconsultations.complete');
            });

        Route::middleware('staff-role:Resepsionis')
            ->name('management.')
            ->group(function (): void {
                Route::get('/doctors', [DoctorManagementController::class, 'index'])->name('doctors.index');
                Route::post('/doctors', [DoctorManagementController::class, 'store'])->name('doctors.store');
            });

        Route::middleware('staff-role:Resepsionis,Admin')
            ->name('reception.')
            ->group(function (): void {
                Route::get('/patients', [ReceptionController::class, 'patients'])->name('patients.index');
                Route::get('/patients/create', [ReceptionController::class, 'createPatient'])->name('patients.create');
                Route::post('/patients', [ReceptionController::class, 'storePatient'])->name('patients.store');
                Route::get('/registrations', [ReceptionController::class, 'registrations'])->name('registrations.index');
                Route::get('/registrations/create', [ReceptionController::class, 'createRegistration'])->name('registrations.create');
                Route::post('/registrations', [ReceptionController::class, 'storeRegistration'])->name('registrations.store');
                Route::post('/registrations/{appointment}/check-in', [ReceptionController::class, 'checkIn'])->name('registrations.check-in');
                Route::post('/registrations/{appointment}/cancel', [ReceptionController::class, 'cancelRegistration'])->name('registrations.cancel');
            });
    });
