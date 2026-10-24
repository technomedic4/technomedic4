<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class DoctorWorkflowController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'date' => ['nullable', 'date'],
        ]);
        $date = $validated['date'] ?? now()->toDateString();
        $doctorId = $this->doctorId($request);

        $appointments = $this->doctorAppointments($doctorId)
            ->leftJoin('patient_care_updates as nursing_assessments', function ($join): void {
                $join->on('nursing_assessments.appointment_id', '=', 'appointments.id')
                    ->whereNull('nursing_assessments.inpatient_admission_id');
            })
            ->where('appointments.consultation_type', 'In-Person')
            ->whereDate('appointments.appointment_date', $date)
            ->select([
                'appointments.id',
                'appointments.booking_code',
                'appointments.appointment_date',
                'appointments.queue_number',
                'appointments.status',
                'appointments.initial_complaint',
                'nursing_assessments.id as nursing_assessment_id',
                'patients.full_name as patient_name',
                'patients.medical_record_number',
                'patients.gender',
                'patients.birth_date',
                'departments.name as department_name',
                'schedules.start_time',
            ])
            ->orderBy('appointments.queue_number')
            ->get();

        return view('staff.doctor.appointments.index', [
            'appointments' => $appointments,
            'date' => $date,
        ]);
    }

    public function show(Request $request, int $appointment): View
    {
        $doctorId = $this->doctorId($request);
        $record = $this->doctorAppointments($doctorId)
            ->where('appointments.consultation_type', 'In-Person')
            ->where('appointments.id', $appointment)
            ->select([
                'appointments.id',
                'appointments.booking_code',
                'appointments.appointment_date',
                'appointments.queue_number',
                'appointments.status',
                'appointments.initial_complaint',
                'patients.full_name as patient_name',
                'patients.medical_record_number',
                'patients.national_id',
                'patients.phone_number',
                'patients.gender',
                'patients.birth_date',
                'patients.address',
                'doctors.full_name as doctor_name',
                'departments.id as department_id',
                'departments.name as department_name',
                'schedules.start_time',
            ])
            ->first();

        abort_if($record === null, 404);

        abort_unless(
            in_array($record->status, ['Checked-In', 'Completed'], true),
            403,
            'Kunjungan dapat diperiksa setelah pasien check-in.'
        );

        $nursingAssessment = DB::table('patient_care_updates as updates')
            ->leftJoin('users', 'users.id', '=', 'updates.recorded_by_user_id')
            ->where('updates.appointment_id', $appointment)
            ->whereNull('updates.inpatient_admission_id')
            ->select([
                'updates.update_status',
                'updates.temperature_celsius',
                'updates.blood_pressure',
                'updates.pulse_rate',
                'updates.respiratory_rate',
                'updates.oxygen_saturation',
                'updates.notes',
                'updates.created_at',
                'users.email as recorded_by',
            ])
            ->first();

        if ($record->status === 'Checked-In') {
            abort_unless(
                $nursingAssessment !== null,
                403,
                'Pemeriksaan awal perawat harus tersimpan sebelum kunjungan diteruskan ke dokter.'
            );
        }

        $consultation = DB::table('doctor_consultations')
            ->leftJoin('departments as referral_departments', 'referral_departments.id', '=', 'doctor_consultations.referral_department_id')
            ->where('appointment_id', $appointment)
            ->select([
                'doctor_consultations.*',
                'referral_departments.name as referral_department_name',
            ])
            ->first();
        $isGeneralPractitioner = in_array($record->department_name, ['Umum', 'Poli Umum'], true);
        $referralDepartments = $isGeneralPractitioner
            ? $this->referralDepartments((int) $record->department_id)
            : collect();

        return view('staff.doctor.appointments.show', [
            'appointment' => $record,
            'nursingAssessment' => $nursingAssessment,
            'consultation' => $consultation,
            'isGeneralPractitioner' => $isGeneralPractitioner,
            'referralDepartments' => $referralDepartments,
        ]);
    }

    public function storeConsultation(Request $request, int $appointment): RedirectResponse
    {
        $doctorId = $this->doctorId($request);
        $doctorDepartment = DB::table('doctors')
            ->join('departments', 'departments.id', '=', 'doctors.department_id')
            ->where('doctors.id', $doctorId)
            ->select(['departments.id', 'departments.name'])
            ->first();
        abort_if($doctorDepartment === null, 403, 'Akun dokter belum terhubung dengan departemen.');

        $isGeneralPractitioner = in_array($doctorDepartment->name, ['Umum', 'Poli Umum'], true);
        $referralDepartmentIds = $isGeneralPractitioner
            ? $this->referralDepartments((int) $doctorDepartment->id)->pluck('id')->map(fn ($id): string => (string) $id)->all()
            : [];

        $validated = $request->validate([
            'diagnosis' => ['required', 'string', 'max:5000'],
            'treatment_plan' => ['required', 'string', 'max:5000'],
            'disposition' => ['required', Rule::in(['Rawat Jalan', 'Rawat Inap'])],
            'referral_department_id' => $isGeneralPractitioner
                ? ['required', Rule::in(['none', ...$referralDepartmentIds])]
                : ['prohibited'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        DB::transaction(function () use ($request, $appointment, $doctorId, $validated): void {
            $record = $this->doctorAppointments($doctorId)
                ->where('appointments.id', $appointment)
                ->where('appointments.consultation_type', 'In-Person')
                ->lockForUpdate()
                ->select([
                    'appointments.id',
                    'appointments.patient_id',
                    'appointments.status',
                ])
                ->first();

            abort_if($record === null, 404);

            if ($record->status !== 'Checked-In') {
                throw ValidationException::withMessages([
                    'appointment' => 'Konsultasi hanya dapat disimpan untuk pasien yang sudah check-in dan belum selesai.',
                ]);
            }

            $nursingAssessmentExists = DB::table('patient_care_updates')
                ->where('appointment_id', $record->id)
                ->whereNull('inpatient_admission_id')
                ->exists();

            if (! $nursingAssessmentExists) {
                throw ValidationException::withMessages([
                    'appointment' => 'Pemeriksaan awal perawat harus tersimpan sebelum dokter dapat memeriksa pasien.',
                ]);
            }

            $patient = DB::table('patient_profiles')
                ->where('id', $record->patient_id)
                ->lockForUpdate()
                ->first();

            abort_if($patient === null, 404);

            if ($validated['disposition'] === 'Rawat Inap') {
                $activeAdmissionExists = DB::table('inpatient_admissions')
                    ->where('patient_id', $record->patient_id)
                    ->whereNull('discharged_at')
                    ->exists();

                if ($activeAdmissionExists) {
                    throw ValidationException::withMessages([
                        'disposition' => 'Pasien sudah tercatat dalam rawat inap aktif.',
                    ]);
                }

                DB::table('inpatient_admissions')->insert([
                    'patient_id' => $record->patient_id,
                    'appointment_id' => $record->id,
                    'room_number' => null,
                    'bed_number' => null,
                    'admission_reason' => $validated['diagnosis'],
                    'admitted_by_user_id' => $request->user()->id,
                    'admitted_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('doctor_consultations')->insert([
                'appointment_id' => $record->id,
                'patient_id' => $record->patient_id,
                'doctor_id' => $doctorId,
                'diagnosis' => $validated['diagnosis'],
                'treatment_plan' => $validated['treatment_plan'],
                'disposition' => $validated['disposition'],
                'referral_department_id' => ($validated['referral_department_id'] ?? 'none') === 'none'
                    ? null
                    : $validated['referral_department_id'],
                'notes' => ($validated['notes'] ?? null) ?: null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('appointments')
                ->where('id', $record->id)
                ->update(['status' => 'Completed']);
        });

        return redirect()
            ->route('staff.doctor.appointments.show', $appointment)
            ->with('success', 'Catatan konsultasi tersimpan dan kunjungan ditandai selesai.');
    }

    private function doctorId(Request $request): int
    {
        $doctorId = DB::table('doctors')
            ->where('user_id', $request->user()->id)
            ->value('id');

        abort_if($doctorId === null, 403, 'Akun dokter belum terhubung dengan profil dokter rumah sakit.');

        return (int) $doctorId;
    }

    private function doctorAppointments(int $doctorId): Builder
    {
        return DB::table('appointments')
            ->join('doctor_schedules as schedules', 'schedules.id', '=', 'appointments.doctor_schedule_id')
            ->join('doctors', 'doctors.id', '=', 'schedules.doctor_id')
            ->join('departments', 'departments.id', '=', 'doctors.department_id')
            ->join('patient_profiles as patients', 'patients.id', '=', 'appointments.patient_id')
            ->where('doctors.id', $doctorId);
    }

    private function referralDepartments(int $currentDepartmentId): Collection
    {
        return DB::table('departments')
            ->join('doctors', 'doctors.department_id', '=', 'departments.id')
            ->where('doctors.specialization', 'like', 'Spesialis%')
            ->where('departments.id', '!=', $currentDepartmentId)
            ->select('departments.id', 'departments.name')
            ->distinct()
            ->orderBy('departments.name')
            ->get();
    }
}
