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

class NursingController extends Controller
{
    private const UPDATE_STATUSES = [
        'Stabil',
        'Membaik',
        'Perlu Pemantauan',
        'Memburuk',
        'Kritis',
    ];

    public function assessmentQueue(Request $request): View
    {
        $validated = $request->validate([
            'date' => ['nullable', 'date'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);
        $date = $validated['date'] ?? now()->toDateString();
        $search = trim((string) ($validated['search'] ?? ''));

        $appointments = $this->waitingAssessments($date, $search)
            ->orderBy('appointments.queue_number')
            ->paginate(15)
            ->withQueryString();

        return view('staff.nursing.assessment-queue', [
            'appointments' => $appointments,
            'date' => $date,
            'search' => $search,
            'refreshUrl' => route('staff.nursing.assessment-queue.refresh', [
                'date' => $date,
                'search' => $search,
                'page' => $appointments->currentPage(),
            ]),
        ]);
    }

    public function refreshAssessmentQueue(Request $request): View
    {
        $validated = $request->validate([
            'date' => ['nullable', 'date'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);
        $date = $validated['date'] ?? now()->toDateString();
        $search = trim((string) ($validated['search'] ?? ''));

        return view('staff.nursing.partials.assessment-queue', [
            'appointments' => $this->waitingAssessments($date, $search)
                ->orderBy('appointments.queue_number')
                ->paginate(15)
                ->withQueryString(),
        ]);
    }

    public function showAssessment(int $appointment): View
    {
        $record = $this->inPersonAppointments()
            ->where('appointments.id', $appointment)
            ->first();

        abort_if($record === null, 404, 'Kunjungan pasien tidak ditemukan.');

        $assessment = DB::table('patient_care_updates as updates')
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

        return view('staff.nursing.assessment', [
            'appointment' => $record,
            'assessment' => $assessment,
            'updateStatuses' => self::UPDATE_STATUSES,
        ]);
    }

    public function storeAssessment(Request $request, int $appointment): RedirectResponse
    {
        $validated = $request->validate([
            'update_status' => ['required', Rule::in(self::UPDATE_STATUSES)],
            'notes' => ['required', 'string', 'max:3000'],
            'temperature_celsius' => ['nullable', 'numeric', 'between:30,45'],
            'blood_pressure' => ['nullable', 'regex:/\A\d{2,3}\/\d{2,3}\z/'],
            'pulse_rate' => ['nullable', 'integer', 'between:20,250'],
            'respiratory_rate' => ['nullable', 'integer', 'between:5,60'],
            'oxygen_saturation' => ['nullable', 'integer', 'between:50,100'],
        ]);
        $vitalSigns = [
            'temperature_celsius',
            'blood_pressure',
            'pulse_rate',
            'respiratory_rate',
            'oxygen_saturation',
        ];

        if (collect($vitalSigns)->every(
            fn (string $field): bool => ($validated[$field] ?? null) === null,
        )) {
            throw ValidationException::withMessages([
                'temperature_celsius' => 'Catat minimal satu tanda vital pasien.',
            ]);
        }

        DB::transaction(function () use ($request, $appointment, $validated): void {
            $record = DB::table('appointments')
                ->where('id', $appointment)
                ->where('consultation_type', 'In-Person')
                ->where('status', 'Checked-In')
                ->lockForUpdate()
                ->first();

            if ($record === null) {
                throw ValidationException::withMessages([
                    'appointment' => 'Asesmen hanya dapat dicatat untuk pasien yang sudah check-in.',
                ]);
            }

            $assessmentExists = DB::table('patient_care_updates')
                ->where('appointment_id', $record->id)
                ->whereNull('inpatient_admission_id')
                ->exists();

            if ($assessmentExists) {
                throw ValidationException::withMessages([
                    'appointment' => 'Pemeriksaan awal perawat untuk kunjungan ini sudah tersimpan.',
                ]);
            }

            DB::table('patient_care_updates')->insert([
                'appointment_id' => $record->id,
                'patient_id' => $record->patient_id,
                'inpatient_admission_id' => null,
                'recorded_by_user_id' => $request->user()->id,
                'update_status' => $validated['update_status'],
                'temperature_celsius' => $validated['temperature_celsius'] ?? null,
                'blood_pressure' => $validated['blood_pressure'] ?? null,
                'pulse_rate' => $validated['pulse_rate'] ?? null,
                'respiratory_rate' => $validated['respiratory_rate'] ?? null,
                'oxygen_saturation' => $validated['oxygen_saturation'] ?? null,
                'notes' => $validated['notes'],
                'created_at' => now(),
            ]);
        });

        return redirect()
            ->route('staff.nursing.assessment-queue.show', $appointment)
            ->with('success', 'Pemeriksaan awal pasien tersimpan dan rekam medis tersedia untuk dokter.');
    }

    public function index(Request $request): View
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
        ]);
        $search = trim((string) ($validated['search'] ?? ''));

        return view('staff.nursing.inpatients', [
            'admissions' => $this->activeAdmissions($search)->paginate(15)->withQueryString(),
            'search' => $search,
        ]);
    }

    public function refresh(Request $request): View
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
        ]);
        $search = trim((string) ($validated['search'] ?? ''));

        return view('staff.nursing.partials.inpatients', [
            'admissions' => $this->activeAdmissions($search)->paginate(15)->withQueryString(),
        ]);
    }

    public function refreshDashboardUpdates(): View
    {
        return view('staff.nursing.partials.dashboard-updates', [
            'careUpdates' => $this->latestCareUpdates(),
        ]);
    }

    public function show(int $admission): View
    {
        $patientAdmission = $this->activeAdmissions()
            ->where('admissions.id', $admission)
            ->first();

        abort_if($patientAdmission === null, 404, 'Data rawat inap aktif tidak ditemukan.');

        return view('staff.nursing.show', [
            'admission' => $patientAdmission,
            'updates' => $this->careUpdates($admission)->get(),
            'updateStatuses' => self::UPDATE_STATUSES,
        ]);
    }

    public function refreshUpdates(int $admission): View
    {
        $this->ensureActiveAdmissionExists($admission);

        return view('staff.nursing.partials.care-updates', [
            'updates' => $this->careUpdates($admission)->get(),
        ]);
    }

    public function storeUpdate(Request $request, int $admission): RedirectResponse
    {
        $validated = $request->validate([
            'update_status' => ['required', Rule::in(self::UPDATE_STATUSES)],
            'notes' => ['required', 'string', 'max:3000'],
            'temperature_celsius' => ['nullable', 'numeric', 'between:30,45'],
            'blood_pressure' => ['nullable', 'regex:/\A\d{2,3}\/\d{2,3}\z/'],
            'pulse_rate' => ['nullable', 'integer', 'between:20,250'],
            'respiratory_rate' => ['nullable', 'integer', 'between:5,60'],
            'oxygen_saturation' => ['nullable', 'integer', 'between:50,100'],
        ]);

        DB::transaction(function () use ($request, $admission, $validated): void {
            $patientAdmission = DB::table('inpatient_admissions')
                ->where('id', $admission)
                ->whereNull('discharged_at')
                ->lockForUpdate()
                ->first();

            if ($patientAdmission === null) {
                throw ValidationException::withMessages([
                    'admission' => 'Pembaruan hanya dapat dicatat untuk pasien yang masih dirawat.',
                ]);
            }

            DB::table('patient_care_updates')->insert([
                'appointment_id' => null,
                'patient_id' => $patientAdmission->patient_id,
                'inpatient_admission_id' => $patientAdmission->id,
                'recorded_by_user_id' => $request->user()->id,
                'update_status' => $validated['update_status'],
                'temperature_celsius' => $validated['temperature_celsius'] ?? null,
                'blood_pressure' => $validated['blood_pressure'] ?? null,
                'pulse_rate' => $validated['pulse_rate'] ?? null,
                'respiratory_rate' => $validated['respiratory_rate'] ?? null,
                'oxygen_saturation' => $validated['oxygen_saturation'] ?? null,
                'notes' => $validated['notes'],
                'created_at' => now(),
            ]);
        });

        return redirect()
            ->route('staff.nursing.inpatients.show', $admission)
            ->with('success', 'Pembaruan kondisi pasien berhasil dicatat.');
    }

    private function activeAdmissions(string $search = ''): Builder
    {
        $latestUpdates = DB::table('patient_care_updates')
            ->selectRaw('inpatient_admission_id, MAX(id) as latest_update_id')
            ->whereNotNull('inpatient_admission_id')
            ->groupBy('inpatient_admission_id');

        return DB::table('inpatient_admissions as admissions')
            ->join('patient_profiles as patients', 'patients.id', '=', 'admissions.patient_id')
            ->leftJoinSub($latestUpdates, 'latest_updates', function ($join): void {
                $join->on('latest_updates.inpatient_admission_id', '=', 'admissions.id');
            })
            ->leftJoin('patient_care_updates as updates', 'updates.id', '=', 'latest_updates.latest_update_id')
            ->whereNull('admissions.discharged_at')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('patients.full_name', 'like', '%'.$search.'%')
                        ->orWhere('patients.medical_record_number', 'like', '%'.$search.'%')
                        ->orWhere('admissions.room_number', 'like', '%'.$search.'%')
                        ->orWhere('admissions.bed_number', 'like', '%'.$search.'%');
                });
            })
            ->select([
                'admissions.id',
                'admissions.patient_id',
                'admissions.room_number',
                'admissions.bed_number',
                'admissions.admission_reason',
                'admissions.admitted_at',
                'patients.full_name as patient_name',
                'patients.medical_record_number',
                'patients.gender',
                'patients.birth_date',
                'updates.update_status as latest_update_status',
                'updates.notes as latest_update_notes',
                'updates.created_at as latest_update_at',
                'updates.temperature_celsius as latest_temperature_celsius',
                'updates.blood_pressure as latest_blood_pressure',
                'updates.pulse_rate as latest_pulse_rate',
                'updates.respiratory_rate as latest_respiratory_rate',
                'updates.oxygen_saturation as latest_oxygen_saturation',
            ])
            ->orderByDesc('admissions.admitted_at');
    }

    private function inPersonAppointments(?string $date = null, string $search = ''): Builder
    {
        return DB::table('appointments')
            ->join('patient_profiles as patients', 'patients.id', '=', 'appointments.patient_id')
            ->join('doctor_schedules as schedules', 'schedules.id', '=', 'appointments.doctor_schedule_id')
            ->join('doctors', 'doctors.id', '=', 'schedules.doctor_id')
            ->join('departments', 'departments.id', '=', 'doctors.department_id')
            ->where('appointments.consultation_type', 'In-Person')
            ->where('appointments.status', 'Checked-In')
            ->when($date !== null, fn ($query) => $query->whereDate('appointments.appointment_date', $date))
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('patients.full_name', 'like', '%'.$search.'%')
                        ->orWhere('patients.medical_record_number', 'like', '%'.$search.'%')
                        ->orWhere('appointments.booking_code', 'like', '%'.$search.'%');
                });
            })
            ->select([
                'appointments.id',
                'appointments.booking_code',
                'appointments.appointment_date',
                'appointments.queue_number',
                'appointments.initial_complaint',
                'patients.id as patient_id',
                'patients.full_name as patient_name',
                'patients.medical_record_number',
                'patients.gender',
                'patients.birth_date',
                'doctors.full_name as doctor_name',
                'departments.name as department_name',
                'schedules.start_time',
            ]);
    }

    private function waitingAssessments(?string $date = null, string $search = ''): Builder
    {
        return $this->inPersonAppointments($date, $search)
            ->whereNotExists(function (Builder $query): void {
                $query->selectRaw('1')
                    ->from('patient_care_updates as nursing_assessments')
                    ->whereColumn('nursing_assessments.appointment_id', 'appointments.id')
                    ->whereNull('nursing_assessments.inpatient_admission_id');
            });
    }

    private function careUpdates(int $admission): Builder
    {
        return DB::table('patient_care_updates as updates')
            ->leftJoin('users', 'users.id', '=', 'updates.recorded_by_user_id')
            ->where('updates.inpatient_admission_id', $admission)
            ->select([
                'updates.id',
                'updates.update_status',
                'updates.notes',
                'updates.temperature_celsius',
                'updates.blood_pressure',
                'updates.pulse_rate',
                'updates.respiratory_rate',
                'updates.oxygen_saturation',
                'updates.created_at',
                'users.email as recorded_by',
            ])
            ->orderByDesc('updates.created_at')
            ->orderByDesc('updates.id')
            ->limit(30);
    }

    private function latestCareUpdates(): Collection
    {
        return DB::table('patient_care_updates as updates')
            ->join('patient_profiles as patients', 'patients.id', '=', 'updates.patient_id')
            ->leftJoin('inpatient_admissions as admissions', 'admissions.id', '=', 'updates.inpatient_admission_id')
            ->leftJoin('appointments', 'appointments.id', '=', 'updates.appointment_id')
            ->leftJoin('users', 'users.id', '=', 'updates.recorded_by_user_id')
            ->where(function ($query): void {
                $query->whereNull('updates.inpatient_admission_id')
                    ->orWhereNull('admissions.discharged_at');
            })
            ->select([
                'patients.full_name as patient_name',
                'admissions.room_number',
                'admissions.bed_number',
                'appointments.booking_code',
                'updates.update_status',
                'updates.notes',
                'updates.temperature_celsius',
                'updates.blood_pressure',
                'updates.pulse_rate',
                'updates.respiratory_rate',
                'updates.oxygen_saturation',
                'updates.created_at',
                'users.email as recorded_by',
            ])
            ->orderByDesc('updates.created_at')
            ->orderByDesc('updates.id')
            ->limit(8)
            ->get();
    }

    private function ensureActiveAdmissionExists(int $admission): void
    {
        abort_unless(
            DB::table('inpatient_admissions')
                ->where('id', $admission)
                ->whereNull('discharged_at')
                ->exists(),
            404,
            'Data rawat inap aktif tidak ditemukan.',
        );
    }
}
