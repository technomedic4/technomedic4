<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Support\AppointmentRegistrationService;
use App\Support\LaboratoryTestCatalog;
use App\Support\PatientConditionStatus;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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
            ->leftJoin('patient_care_updates as patient_conditions', function ($join): void {
                $join->on('patient_conditions.appointment_id', '=', 'appointments.id')
                    ->whereNull('patient_conditions.inpatient_admission_id');
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
                'patient_conditions.id as patient_condition_id',
                'patient_conditions.recorded_by_user_id as patient_condition_recorded_by_user_id',
                'doctors.user_id as doctor_user_id',
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

    public function inpatients(Request $request): View
    {
        $doctorId = $this->doctorId($request);
        $admissions = $this->doctorInpatientAdmissions($doctorId)
            ->select([
                'admissions.id',
                'admissions.room_number',
                'admissions.bed_number',
                'admissions.admission_reason',
                'admissions.admitted_at',
                'admissions.doctor_discharge_approved_at',
                'appointments.booking_code',
                'patients.full_name as patient_name',
                'patients.medical_record_number',
                'consultations.diagnosis',
                'consultations.treatment_plan',
            ])
            ->orderByDesc('admissions.admitted_at')
            ->paginate(15)
            ->withQueryString();

        return view('staff.doctor.inpatients.index', compact('admissions'));
    }

    public function showInpatient(Request $request, int $admission): View
    {
        $doctorId = $this->doctorId($request);
        $patientAdmission = $this->doctorInpatientAdmissions($doctorId)
            ->where('admissions.id', $admission)
            ->select([
                'admissions.id',
                'admissions.patient_id',
                'admissions.appointment_id',
                'admissions.room_number',
                'admissions.bed_number',
                'admissions.admission_reason',
                'admissions.admitted_at',
                'admissions.doctor_discharge_approved_at',
                'patients.full_name as patient_name',
                'patients.medical_record_number',
                'patients.gender',
                'patients.birth_date',
                'consultations.diagnosis',
                'consultations.treatment_plan',
            ])
            ->first();

        abort_if($patientAdmission === null, 404);

        $updates = DB::table('patient_care_updates as updates')
            ->leftJoin('users', 'users.id', '=', 'updates.recorded_by_user_id')
            ->leftJoin('roles', 'roles.id', '=', 'users.role_id')
            ->where('updates.inpatient_admission_id', $admission)
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
                'roles.role_name as recorded_by_role',
            ])
            ->orderByDesc('updates.created_at')
            ->orderByDesc('updates.id')
            ->paginate(30);

        return view('staff.doctor.inpatients.show', [
            'admission' => $patientAdmission,
            'updates' => $updates,
            'updateStatuses' => PatientConditionStatus::values(),
        ]);
    }

    public function storeInpatientEvaluation(Request $request, int $admission): RedirectResponse
    {
        $validated = $request->validate([
            'diagnosis' => ['required', 'string', 'max:5000'],
            'treatment_plan' => ['required', 'string', 'max:5000'],
            'discharge_decision' => ['required', Rule::in(['Continue', 'Approve'])],
        ]);
        $doctorId = $this->doctorId($request);

        DB::transaction(function () use ($request, $admission, $doctorId, $validated): void {
            $patientAdmission = $this->doctorInpatientAdmissions($doctorId)
                ->where('admissions.id', $admission)
                ->lockForUpdate()
                ->select([
                    'admissions.id',
                    'admissions.appointment_id',
                ])
                ->first();

            abort_if($patientAdmission === null, 404);

            $consultation = DB::table('doctor_consultations')
                ->where('appointment_id', $patientAdmission->appointment_id)
                ->where('doctor_id', $doctorId)
                ->lockForUpdate()
                ->first();
            abort_if($consultation === null, 404);

            $now = now();

            DB::table('doctor_consultations')
                ->where('id', $consultation->id)
                ->update([
                    'diagnosis' => $validated['diagnosis'],
                    'treatment_plan' => $validated['treatment_plan'],
                    'updated_at' => $now,
                ]);

            DB::table('inpatient_admissions')
                ->where('id', $patientAdmission->id)
                ->update([
                    'doctor_discharge_approved_by_user_id' => $validated['discharge_decision'] === 'Approve'
                        ? $request->user()->id
                        : null,
                    'doctor_discharge_approved_at' => $validated['discharge_decision'] === 'Approve'
                        ? $now
                        : null,
                    'updated_at' => $now,
                ]);
        });

        $successMessage = $validated['discharge_decision'] === 'Approve'
            ? 'Diagnosis dan rencana pengobatan tersimpan; kepulangan pasien divalidasi untuk proses administrasi.'
            : 'Diagnosis dan rencana pengobatan tersimpan; pasien tetap perlu dirawat.';

        return redirect()
            ->route('staff.doctor.inpatients.show', $admission)
            ->with('success', $successMessage);
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

    public function laboratoryResults(Request $request): View
    {
        $doctorId = $this->doctorId($request);
        $labOrders = $this->doctorLaboratoryOrders($doctorId)
            ->select([
                'laboratory_orders.id',
                'laboratory_orders.status',
                'laboratory_orders.requested_tests',
                'laboratory_orders.created_at',
                'appointments.appointment_date',
                'appointments.booking_code',
                'patients.full_name as patient_name',
                'patients.medical_record_number',
            ])
            ->orderByDesc('laboratory_orders.created_at')
            ->orderByDesc('laboratory_orders.id')
            ->paginate(15);

        return view('staff.doctor.laboratory.index', compact('labOrders'));
    }

    public function laboratoryReport(Request $request, int $labOrder): View
    {
        $doctorId = $this->doctorId($request);
        $report = $this->doctorLaboratoryOrders($doctorId)
            ->where('laboratory_orders.id', $labOrder)
            ->select([
                'laboratory_orders.*',
                'appointments.appointment_date',
                'appointments.booking_code',
                'patients.full_name as patient_name',
                'patients.medical_record_number',
                'patients.gender',
                'patients.birth_date',
                'consultations.diagnosis',
                'referring_doctors.full_name as referring_doctor_name',
                'referring_departments.name as referring_department_name',
                'lab_users.email as resulted_by_email',
            ])
            ->first();

        abort_if($report === null, 404);

        $laboratoryResults = DB::table('laboratory_results')
            ->where('laboratory_order_id', $labOrder)
            ->orderBy('sort_order')
            ->get();
        $laboratoryResultGroups = LaboratoryTestCatalog::resultGroups($laboratoryResults);

        return view('staff.doctor.laboratory.report', compact('report', 'laboratoryResultGroups'));
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
                'doctors.user_id as doctor_user_id',
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

        $patientCondition = $record->status === 'Pending' || $record->status === 'Confirmed'
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
                    'updates.recorded_by_user_id',
                    'users.email as recorded_by',
                ])
                ->first();

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
        $prescription = $consultation === null
            ? null
            : DB::table('prescriptions')
                ->join('bills', 'bills.id', '=', 'prescriptions.bill_id')
                ->where('prescriptions.consultation_id', $consultation->id)
                ->select([
                    'prescriptions.id',
                    'prescriptions.dispensed_at',
                    'bills.bill_number',
                    'bills.status as bill_status',
                ])
                ->first();
        $prescriptionItems = $prescription === null
            ? collect()
            : DB::table('prescription_items')
                ->where('prescription_id', $prescription->id)
                ->orderBy('id')
                ->get();
        $laboratoryOrder = $consultation === null
            ? null
            : DB::table('laboratory_orders')
                ->where('consultation_id', $consultation->id)
                ->first();
        $laboratoryResultGroups = $laboratoryOrder === null
            ? []
            : LaboratoryTestCatalog::resultGroups(
                DB::table('laboratory_results')
                    ->where('laboratory_order_id', $laboratoryOrder->id)
                    ->orderBy('sort_order')
                    ->get()
            );
        $laboratoryRequestedGroups = $laboratoryOrder === null || ! $laboratoryOrder->requested_test_codes
            ? []
            : LaboratoryTestCatalog::requestedGroups(
                json_decode($laboratoryOrder->requested_test_codes, true, 512, JSON_THROW_ON_ERROR)
            );
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
            'laboratoryTestGroups' => LaboratoryTestCatalog::groups(),
            'patientCondition' => $patientCondition,
            'patientConditionStatuses' => PatientConditionStatus::values(),
            'consultation' => $consultation,
            'prescription' => $prescription,
            'prescriptionItems' => $prescriptionItems,
            'procedureCatalog' => DB::table('procedure_catalog')
                ->where('is_active', true)
                ->orderBy('category')
                ->orderBy('name')
                ->get(['id', 'category', 'name', 'price']),
            'medicineCatalog' => DB::table('medicine_catalog')
                ->where('is_active', true)
                ->orderBy('generic_name')
                ->orderBy('strength')
                ->get(['id', 'generic_name', 'strength', 'dosage_form', 'unit', 'price', 'price_is_estimate']),
            'laboratoryOrder' => $laboratoryOrder,
            'laboratoryRequestedGroups' => $laboratoryRequestedGroups,
            'laboratoryResultGroups' => $laboratoryResultGroups,
            'referral' => $referral,
            'referralNursingAssessment' => $referralNursingAssessment,
            'referralSlots' => $referralSlots,
        ]);
    }

    public function storePatientCondition(Request $request, int $appointment): RedirectResponse
    {
        $validated = $request->validate([
            'update_status' => ['required', Rule::in(PatientConditionStatus::values())],
            'notes' => ['required', 'string', 'max:3000'],
        ]);

        $doctorId = $this->doctorId($request);

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
                    'appointment' => 'Kondisi pasien hanya dapat divalidasi selama kunjungan berlangsung.',
                ]);
            }

            $existingCondition = DB::table('patient_care_updates')
                ->where('appointment_id', $record->id)
                ->whereNull('inpatient_admission_id')
                ->lockForUpdate()
                ->first();
            $vitalSigns = [
                'temperature_celsius',
                'blood_pressure',
                'pulse_rate',
                'respiratory_rate',
                'oxygen_saturation',
            ];

            if ($existingCondition === null || collect($vitalSigns)->every(
                fn (string $field): bool => $existingCondition->{$field} === null,
            )) {
                throw ValidationException::withMessages([
                    'appointment' => 'Perawat harus mencatat minimal satu tanda vital sebelum pemeriksaan dokter.',
                ]);
            }

            $now = now();

            DB::table('patient_care_updates')
                ->where('id', $existingCondition->id)
                ->update([
                    'recorded_by_user_id' => $request->user()->id,
                    'update_status' => $validated['update_status'],
                    'notes' => $validated['notes'],
                    'created_at' => $now,
                ]);
        });

        return redirect()
            ->route('staff.doctor.appointments.show', $appointment)
            ->with('success', 'Kondisi pasien berhasil divalidasi.');
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
            'procedure_ids' => ['sometimes', 'array', 'max:50'],
            'procedure_ids.*' => ['required', 'integer', 'distinct', 'exists:procedure_catalog,id'],
            'prescription_items' => ['sometimes', 'array', 'max:50'],
            'prescription_items.*.medicine_id' => ['required', 'integer', 'distinct', 'exists:medicine_catalog,id'],
            'prescription_items.*.quantity' => ['required', 'integer', 'min:1', 'max:1000'],
            'prescription_items.*.dosage' => ['required', 'string', 'max:150'],
            'prescription_items.*.instructions' => ['required', 'string', 'max:500'],
            'referral_slot' => ['required', Rule::in(['none', ...$referralSlotValues])],
            'referral_reason' => $referralRequested
                ? ['required', 'string', 'max:5000']
                : ['nullable', 'prohibited'],
            'examination_findings' => $referralRequested
                ? ['required', 'string', 'max:5000']
                : ['nullable', 'prohibited'],
            'laboratory_requested' => ['sometimes', 'boolean'],
            'requested_test_codes' => [
                Rule::requiredIf($request->boolean('laboratory_requested')),
                Rule::prohibitedIf(! $request->boolean('laboratory_requested')),
                'array',
                'min:1',
                'max:'.count(LaboratoryTestCatalog::tests()),
            ],
            'requested_test_codes.*' => [
                'required',
                'string',
                'distinct',
                Rule::in(array_keys(LaboratoryTestCatalog::tests())),
            ],
            'laboratory_notes' => [
                Rule::prohibitedIf(! $request->boolean('laboratory_requested')),
                'nullable',
                'string',
                'max:3000',
            ],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $requestedTests = $request->boolean('laboratory_requested')
            ? collect($validated['requested_test_codes'])
                ->map(fn (string $code): string => LaboratoryTestCatalog::tests()[$code])
                ->implode("\n")
            : null;

        $hasBill = DB::transaction(function () use ($request, $appointment, $doctorId, $validated, $referralSlots, $appointmentRegistration, $requestedTests): bool {
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

            $conditionValidatedByDoctor = DB::table('patient_care_updates')
                ->where('appointment_id', $record->id)
                ->whereNull('inpatient_admission_id')
                ->where('recorded_by_user_id', $request->user()->id)
                ->exists();

            if (! $conditionValidatedByDoctor) {
                throw ValidationException::withMessages([
                    'appointment' => 'Validasi kondisi pasien dan tanda vital harus disimpan sebelum konsultasi.',
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

            $hasBill = $this->createConsultationBill(
                (int) $consultationId,
                (int) $record->patient_id,
                $doctorId,
                (int) $request->user()->id,
                $validated['procedure_ids'] ?? [],
                $validated['prescription_items'] ?? [],
            );

            if ($request->boolean('laboratory_requested')) {
                DB::table('laboratory_orders')->insert([
                    'consultation_id' => $consultationId,
                    'patient_id' => $record->patient_id,
                    'referring_doctor_id' => $doctorId,
                    'requested_tests' => $requestedTests,
                    'requested_test_codes' => json_encode($validated['requested_test_codes'], JSON_THROW_ON_ERROR),
                    'clinical_notes' => $validated['laboratory_notes'] ?? null,
                    'status' => 'Pending',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

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

            return $hasBill;
        });

        return redirect()
            ->route('staff.doctor.appointments.show', $appointment)
            ->with(
                'success',
                $hasBill
                    ? 'Catatan konsultasi tersimpan; rincian tagihan telah dikirim ke bagian keuangan.'
                    : 'Catatan konsultasi tersimpan dan kunjungan ditandai selesai.',
            );
    }

    /**
     * @param  list<string|int>  $procedureIds
     * @param  list<array{medicine_id: string|int, quantity: string|int, dosage: string, instructions: string}>  $prescriptionItems
     */
    private function createConsultationBill(
        int $consultationId,
        int $patientId,
        int $doctorId,
        int $userId,
        array $procedureIds,
        array $prescriptionItems,
    ): bool {
        if ($procedureIds === [] && $prescriptionItems === []) {
            return false;
        }

        $procedures = DB::table('procedure_catalog')
            ->whereIn('id', $procedureIds)
            ->where('is_active', true)
            ->lockForUpdate()
            ->get(['id', 'name', 'price'])
            ->keyBy('id');
        $medicineIds = array_map(
            static fn (array $item): int => (int) $item['medicine_id'],
            $prescriptionItems,
        );
        $medicines = $medicineIds === []
            ? collect()
            : DB::table('medicine_catalog')
                ->whereIn('id', $medicineIds)
                ->where('is_active', true)
                ->lockForUpdate()
                ->get(['id', 'generic_name', 'strength', 'dosage_form', 'unit', 'price', 'price_is_estimate'])
                ->keyBy('id');

        if ($procedures->count() !== count($procedureIds) || $medicines->count() !== count($medicineIds)) {
            throw ValidationException::withMessages([
                'procedure_ids' => 'Ada tindakan atau obat yang sudah tidak tersedia pada katalog.',
            ]);
        }

        $totalAmount = 0;

        foreach ($procedureIds as $procedureId) {
            $totalAmount += (int) $procedures->get((int) $procedureId)->price;
        }

        foreach ($prescriptionItems as $item) {
            $medicine = $medicines->get((int) $item['medicine_id']);
            $totalAmount += (int) $medicine->price * (int) $item['quantity'];
        }

        $now = now();
        $billId = DB::table('bills')->insertGetId([
            'bill_number' => 'INV-'.$now->format('Ymd').'-'.Str::upper(Str::random(8)),
            'patient_id' => $patientId,
            'total_amount' => $totalAmount,
            'amount_paid' => 0,
            'status' => 'Unpaid',
            'created_by_user_id' => $userId,
            'source_consultation_id' => $consultationId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        foreach ($procedureIds as $procedureId) {
            $procedure = $procedures->get((int) $procedureId);

            DB::table('bill_items')->insert([
                'bill_id' => $billId,
                'item_name' => $procedure->name,
                'quantity' => 1,
                'unit_price' => $procedure->price,
                'procedure_catalog_id' => $procedure->id,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        if ($prescriptionItems !== []) {
            $prescriptionId = DB::table('prescriptions')->insertGetId([
                'consultation_id' => $consultationId,
                'patient_id' => $patientId,
                'doctor_id' => $doctorId,
                'bill_id' => $billId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            foreach ($prescriptionItems as $item) {
                $medicine = $medicines->get((int) $item['medicine_id']);
                $medicineName = trim($medicine->generic_name.' '.($medicine->strength ?? '').' '.($medicine->dosage_form ?? ''));

                DB::table('bill_items')->insert([
                    'bill_id' => $billId,
                    'item_name' => Str::limit(
                        $medicineName.($medicine->price_is_estimate ? ' (harga estimasi)' : ''),
                        150,
                        '',
                    ),
                    'quantity' => $item['quantity'],
                    'unit_price' => $medicine->price,
                    'medicine_catalog_id' => $medicine->id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                DB::table('prescription_items')->insert([
                    'prescription_id' => $prescriptionId,
                    'medicine_id' => $medicine->id,
                    'medicine_name' => $medicine->generic_name,
                    'strength' => $medicine->strength,
                    'dosage_form' => $medicine->dosage_form,
                    'unit' => $medicine->unit,
                    'quantity' => $item['quantity'],
                    'dosage' => trim($item['dosage']),
                    'instructions' => trim($item['instructions']),
                    'unit_price' => $medicine->price,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        return true;
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

    private function doctorLaboratoryOrders(int $doctorId): Builder
    {
        return DB::table('laboratory_orders')
            ->join('doctor_consultations as consultations', 'consultations.id', '=', 'laboratory_orders.consultation_id')
            ->join('appointments', 'appointments.id', '=', 'consultations.appointment_id')
            ->join('patient_profiles as patients', 'patients.id', '=', 'laboratory_orders.patient_id')
            ->join('doctors as referring_doctors', 'referring_doctors.id', '=', 'laboratory_orders.referring_doctor_id')
            ->join('departments as referring_departments', 'referring_departments.id', '=', 'referring_doctors.department_id')
            ->leftJoin('users as lab_users', 'lab_users.id', '=', 'laboratory_orders.resulted_by_user_id')
            ->where('consultations.doctor_id', $doctorId);
    }

    private function doctorInpatientAdmissions(int $doctorId): Builder
    {
        return DB::table('inpatient_admissions as admissions')
            ->join('appointments', 'appointments.id', '=', 'admissions.appointment_id')
            ->join('doctor_consultations as consultations', 'consultations.appointment_id', '=', 'appointments.id')
            ->join('doctors', 'doctors.id', '=', 'consultations.doctor_id')
            ->join('patient_profiles as patients', 'patients.id', '=', 'admissions.patient_id')
            ->where('consultations.doctor_id', $doctorId)
            ->whereNull('admissions.discharged_at');
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
