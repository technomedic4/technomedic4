<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Support\AppointmentRegistrationService;
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
    private const DAYS_OF_WEEK = [
        1 => 'Senin',
        2 => 'Selasa',
        3 => 'Rabu',
        4 => 'Kamis',
        5 => 'Jumat',
        6 => 'Sabtu',
        7 => 'Minggu',
    ];

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
                'appointments.referral_consultation_id',
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

    public function referrals(Request $request): View
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
        ]);
        $search = trim($validated['search'] ?? '');
        $doctorId = $this->doctorId($request);

        $appointments = $this->doctorAppointments($doctorId)
            ->join('doctor_consultations as referrals', 'referrals.id', '=', 'appointments.referral_consultation_id')
            ->join('doctors as referring_doctors', 'referring_doctors.id', '=', 'referrals.doctor_id')
            ->join('departments as referring_departments', 'referring_departments.id', '=', 'referring_doctors.department_id')
            ->whereNotNull('appointments.referral_consultation_id')
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where('patients.full_name', 'like', '%'.$search.'%');
            })
            ->select([
                'appointments.id',
                'appointments.appointment_date',
                'appointments.queue_number',
                'appointments.status',
                'appointments.booking_code',
                'patients.full_name as patient_name',
                'patients.medical_record_number',
                'departments.name as receiving_department_name',
                'schedules.start_time',
                'referrals.referral_reason',
                'referrals.examination_findings',
                'referrals.diagnosis',
                'referrals.treatment_plan',
                'referring_doctors.full_name as referring_doctor_name',
                'referring_departments.name as referring_department_name',
            ])
            ->orderByDesc('appointments.appointment_date')
            ->orderBy('appointments.queue_number')
            ->paginate(15)
            ->withQueryString();

        return view('staff.doctor.referrals.index', compact('appointments', 'search'));
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
                'appointments.referral_consultation_id',
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
            in_array($record->status, ['Checked-In', 'Completed'], true)
                || ($record->referral_consultation_id !== null && in_array($record->status, ['Pending', 'Confirmed'], true)),
            403,
            'Kunjungan dapat diperiksa setelah pasien check-in.'
        );

        $nursingAssessment = $record->status === 'Pending' || $record->status === 'Confirmed'
            ? null
            : DB::table('patient_care_updates as updates')
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
            ->leftJoin('doctors as referral_doctors', 'referral_doctors.id', '=', 'doctor_consultations.referral_doctor_id')
            ->where('appointment_id', $appointment)
            ->select([
                'doctor_consultations.*',
                'referral_departments.name as referral_department_name',
                'referral_doctors.full_name as referral_doctor_name',
            ])
            ->first();
        $referral = $record->referral_consultation_id === null
            ? null
            : DB::table('doctor_consultations as referrals')
                ->join('doctors as referring_doctors', 'referring_doctors.id', '=', 'referrals.doctor_id')
                ->join('departments as referring_departments', 'referring_departments.id', '=', 'referring_doctors.department_id')
                ->join('appointments as referring_appointments', 'referring_appointments.id', '=', 'referrals.appointment_id')
                ->where('referrals.id', $record->referral_consultation_id)
                ->select([
                    'referrals.referral_reason',
                    'referrals.examination_findings',
                    'referrals.diagnosis',
                    'referrals.treatment_plan',
                    'referrals.notes',
                    'referring_doctors.full_name as referring_doctor_name',
                    'referring_departments.name as referring_department_name',
                    'referring_appointments.id as referring_appointment_id',
                ])
                ->first();
        $referralNursingAssessment = $referral === null
            ? null
            : DB::table('patient_care_updates')
                ->where('appointment_id', $referral->referring_appointment_id)
                ->whereNull('inpatient_admission_id')
                ->select([
                    'temperature_celsius',
                    'blood_pressure',
                    'pulse_rate',
                    'respiratory_rate',
                    'oxygen_saturation',
                    'notes',
                ])
                ->first();
        $referralSlots = $this->availableReferralSlots((int) $record->department_id);

        return view('staff.doctor.appointments.show', [
            'appointment' => $record,
            'nursingAssessment' => $nursingAssessment,
            'consultation' => $consultation,
            'referral' => $referral,
            'referralNursingAssessment' => $referralNursingAssessment,
            'referralSlots' => $referralSlots,
        ]);
    }

    public function storeConsultation(
        Request $request,
        int $appointment,
        AppointmentRegistrationService $appointmentRegistration,
    ): RedirectResponse {
        $doctorId = $this->doctorId($request);
        $doctorDepartment = DB::table('doctors')
            ->join('departments', 'departments.id', '=', 'doctors.department_id')
            ->where('doctors.id', $doctorId)
            ->select(['departments.id', 'departments.name'])
            ->first();
        abort_if($doctorDepartment === null, 403, 'Akun dokter belum terhubung dengan departemen.');

        $referralSlots = $this->availableReferralSlots((int) $doctorDepartment->id);
        $referralSlotValues = $referralSlots->pluck('value')->all();
        $referralRequested = $request->input('referral_slot') !== 'none';

        $validated = $request->validate([
            'diagnosis' => ['required', 'string', 'max:5000'],
            'treatment_plan' => ['required', 'string', 'max:5000'],
            'disposition' => ['required', Rule::in(['Rawat Jalan', 'Rawat Inap'])],
            'referral_slot' => ['required', Rule::in(['none', ...$referralSlotValues])],
            'referral_reason' => $referralRequested
                ? ['required', 'string', 'max:5000']
                : ['nullable', 'prohibited'],
            'examination_findings' => $referralRequested
                ? ['required', 'string', 'max:5000']
                : ['nullable', 'prohibited'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        DB::transaction(function () use ($request, $appointment, $doctorId, $validated, $referralSlots, $appointmentRegistration): void {
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

            $referralSlot = $validated['referral_slot'] === 'none'
                ? null
                : $referralSlots->firstWhere('value', $validated['referral_slot']);
            $referralSchedule = $referralSlot === null
                ? null
                : DB::table('doctor_schedules')
                    ->join('doctors', 'doctors.id', '=', 'doctor_schedules.doctor_id')
                    ->where('doctor_schedules.id', $referralSlot->schedule_id)
                    ->select(['doctors.id as doctor_id', 'doctors.department_id'])
                    ->first();

            $consultationId = DB::table('doctor_consultations')->insertGetId([
                'appointment_id' => $record->id,
                'patient_id' => $record->patient_id,
                'doctor_id' => $doctorId,
                'diagnosis' => $validated['diagnosis'],
                'treatment_plan' => $validated['treatment_plan'],
                'disposition' => $validated['disposition'],
                'referral_department_id' => $referralSchedule?->department_id,
                'referral_doctor_id' => $referralSchedule?->doctor_id,
                'referral_reason' => $validated['referral_reason'] ?? null,
                'examination_findings' => $validated['examination_findings'] ?? null,
                'notes' => ($validated['notes'] ?? null) ?: null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if ($referralSlot !== null) {
                $referralAppointmentId = $appointmentRegistration->create(
                    (int) $record->patient_id,
                    (int) $referralSlot->schedule_id,
                    $referralSlot->date,
                    $validated['referral_reason'],
                    'In-Person',
                );

                DB::table('appointments')
                    ->where('id', $referralAppointmentId)
                    ->update(['referral_consultation_id' => $consultationId]);
            }

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

    private function availableReferralSlots(int $currentDepartmentId): Collection
    {
        $schedules = DB::table('doctor_schedules')
            ->join('doctors', 'doctors.id', '=', 'doctor_schedules.doctor_id')
            ->join('departments', 'departments.id', '=', 'doctors.department_id')
            ->where('doctors.specialization', 'like', 'Spesialis%')
            ->where('doctors.department_id', '!=', $currentDepartmentId)
            ->select([
                'doctor_schedules.id',
                'doctor_schedules.day_of_week',
                'doctor_schedules.start_time',
                'doctor_schedules.quota_limit',
                'doctors.id as doctor_id',
                'doctors.full_name as doctor_name',
                'departments.name as department_name',
            ])
            ->orderBy('departments.name')
            ->orderBy('doctors.full_name')
            ->orderBy('doctor_schedules.start_time')
            ->get();

        if ($schedules->isEmpty()) {
            return collect();
        }

        $startDate = now()->startOfDay();
        $endDate = $startDate->copy()->addDays(59);
        $scheduleIds = $schedules->pluck('id');
        $activeAppointments = DB::table('appointments')
            ->whereIn('doctor_schedule_id', $scheduleIds)
            ->whereBetween('appointment_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->where('status', '!=', 'Cancelled')
            ->get(['doctor_schedule_id', 'appointment_date'])
            ->countBy(fn ($appointment): string => $appointment->doctor_schedule_id.'|'.$appointment->appointment_date);
        $slots = collect();

        for ($offset = 0; $offset <= 59; $offset++) {
            $date = $startDate->copy()->addDays($offset);
            $dayOfWeek = self::DAYS_OF_WEEK[$date->dayOfWeekIso];

            foreach ($schedules->where('day_of_week', $dayOfWeek) as $schedule) {
                $bookedCount = $activeAppointments->get($schedule->id.'|'.$date->toDateString(), 0);

                if ($bookedCount >= $schedule->quota_limit) {
                    continue;
                }

                $slots->push((object) [
                    'value' => $schedule->id.'|'.$date->toDateString(),
                    'schedule_id' => (int) $schedule->id,
                    'date' => $date->toDateString(),
                    'label' => $schedule->department_name.' · '.$schedule->doctor_name.' · '.$date->locale('id')->translatedFormat('d M Y').' · '.substr($schedule->start_time, 0, 5),
                ]);
            }
        }

        return $slots;
    }
}
