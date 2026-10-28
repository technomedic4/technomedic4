<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\Staff\BillingController;
use App\Http\Controllers\Staff\DoctorManagementController;
use App\Http\Controllers\Staff\DoctorWorkflowController;
use App\Http\Controllers\Staff\InpatientAdmissionController;
use App\Http\Controllers\Staff\MedicalRecordController;
use App\Http\Controllers\Staff\NurseManagementController;
use App\Http\Controllers\Staff\NursingController;
use App\Http\Controllers\Staff\ReceptionController;
use App\Http\Controllers\StaffDashboardController;
use App\Http\Controllers\TeleconsultationController;
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

        Route::middleware('staff-role:Perawat')
            ->prefix('nursing')
            ->name('nursing.')
            ->group(function (): void {
                Route::get('/updates', [NursingController::class, 'refreshDashboardUpdates'])->name('updates');
                Route::get('/assessment-queue', [NursingController::class, 'assessmentQueue'])->name('assessment-queue.index');
                Route::get('/assessment-queue/refresh', [NursingController::class, 'refreshAssessmentQueue'])->name('assessment-queue.refresh');
                Route::get('/assessment-queue/{appointment}', [NursingController::class, 'showAssessment'])->name('assessment-queue.show');
                Route::post('/assessment-queue/{appointment}', [NursingController::class, 'storeAssessment'])->name('assessment-queue.assessments.store');
                Route::get('/inpatients', [NursingController::class, 'index'])->name('inpatients.index');
                Route::get('/inpatients/refresh', [NursingController::class, 'refresh'])->name('inpatients.refresh');
                Route::get('/inpatients/{admission}', [NursingController::class, 'show'])->name('inpatients.show');
                Route::get('/inpatients/{admission}/updates', [NursingController::class, 'refreshUpdates'])->name('inpatients.updates');
                Route::post('/inpatients/{admission}/updates', [NursingController::class, 'storeUpdate'])->name('inpatients.updates.store');
                Route::post('/inpatients/{admission}/treatments', [NursingController::class, 'storeTreatment'])->name('inpatients.treatments.store');
                Route::get('/billing', [NursingController::class, 'billingIndex'])->name('billing.index');
                Route::get('/billing/{bill}', [NursingController::class, 'billingShow'])->whereNumber('bill')->name('billing.show');
            });

        Route::middleware('staff-role:Resepsionis,Admin')
            ->group(function (): void {
                Route::get('/inpatient-admissions', [InpatientAdmissionController::class, 'index'])->name('inpatient-admissions.index');
                Route::post('/inpatient-admissions/{admission}/room', [InpatientAdmissionController::class, 'assignRoom'])->name('inpatient-admissions.room.assign');
                Route::post('/inpatient-admissions/{admission}/discharge', [InpatientAdmissionController::class, 'discharge'])->name('inpatient-admissions.discharge');
            });

        Route::middleware('staff-role:Resepsionis,Keuangan,Admin')
            ->prefix('billing')
            ->name('billing.')
            ->group(function (): void {
                Route::get('/', [BillingController::class, 'index'])->name('index');
                Route::get('/create', [BillingController::class, 'create'])->name('create');
                Route::post('/', [BillingController::class, 'store'])->name('store');
                Route::get('/{bill}', [BillingController::class, 'show'])->whereNumber('bill')->name('show');
                Route::get('/{bill}/edit', [BillingController::class, 'edit'])->whereNumber('bill')->name('edit');
                Route::put('/{bill}', [BillingController::class, 'update'])->whereNumber('bill')->name('update');
                Route::delete('/{bill}', [BillingController::class, 'destroy'])->whereNumber('bill')->name('destroy');
                Route::post('/{bill}/payments', [BillingController::class, 'recordPayment'])->whereNumber('bill')->name('payments.store');
                Route::get('/{bill}/payment-submissions/{submission}/proof', [BillingController::class, 'paymentProof'])
                    ->whereNumber('bill')
                    ->whereNumber('submission')
                    ->name('payment-submissions.proof');
                Route::post('/{bill}/payment-submissions/{submission}/review', [BillingController::class, 'reviewPaymentSubmission'])
                    ->whereNumber('bill')
                    ->whereNumber('submission')
                    ->name('payment-submissions.review');
                Route::post('/{bill}/cancel', [BillingController::class, 'cancel'])->whereNumber('bill')->name('cancel');
            });

        Route::middleware('staff-role:Resepsionis,Admin')
            ->prefix('management/nurses')
            ->name('management.nurses.')
            ->group(function (): void {
                Route::get('/', [NurseManagementController::class, 'index'])->name('index');
                Route::post('/', [NurseManagementController::class, 'store'])->name('store');
            });

        Route::middleware('staff-role:Resepsionis,Admin,Dokter')
            ->get('/medical-records', [MedicalRecordController::class, 'index'])
            ->name('medical-records.index');
        Route::middleware('staff-role:Resepsionis,Admin,Dokter')
            ->get('/medical-records/{patient}', [MedicalRecordController::class, 'show'])
            ->name('medical-records.show');
        Route::middleware('staff-role:Resepsionis,Admin,Dokter')
            ->get('/records', [MedicalRecordController::class, 'index'])
            ->name('records.index');
        Route::middleware('staff-role:Resepsionis,Admin,Dokter')
            ->get('/records/{patient}', [MedicalRecordController::class, 'show'])
            ->name('records.show');

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
