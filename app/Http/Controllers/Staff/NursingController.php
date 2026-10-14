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
            ->join('inpatient_admissions as admissions', 'admissions.id', '=', 'updates.inpatient_admission_id')
            ->join('patient_profiles as patients', 'patients.id', '=', 'admissions.patient_id')
            ->leftJoin('users', 'users.id', '=', 'updates.recorded_by_user_id')
            ->whereNull('admissions.discharged_at')
            ->select([
                'patients.full_name as patient_name',
                'admissions.room_number',
                'admissions.bed_number',
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
