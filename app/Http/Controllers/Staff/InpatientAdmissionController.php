<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InpatientAdmissionController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
        ]);
        $search = trim((string) ($validated['search'] ?? ''));

        $admissions = DB::table('inpatient_admissions as admissions')
            ->join('patient_profiles as patients', 'patients.id', '=', 'admissions.patient_id')
            ->join('users as admitted_by', 'admitted_by.id', '=', 'admissions.admitted_by_user_id')
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
                'admissions.room_number',
                'admissions.bed_number',
                'admissions.admission_reason',
                'admissions.admitted_at',
                'patients.full_name as patient_name',
                'patients.medical_record_number',
                'admitted_by.email as admitted_by',
            ])
            ->orderByDesc('admissions.admitted_at')
            ->paginate(15)
            ->withQueryString();

        return view('staff.inpatient-admissions.index', compact('admissions', 'search'));
    }

    public function create(Request $request): View
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'patient_id' => ['nullable', 'integer', 'exists:patient_profiles,id'],
        ]);
        $search = trim((string) ($validated['search'] ?? ''));

        $patients = DB::table('patient_profiles')
            ->select(['id', 'medical_record_number', 'full_name'])
            ->when(
                isset($validated['patient_id']),
                fn ($query) => $query->where('id', $validated['patient_id']),
            )
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('full_name', 'like', '%'.$search.'%')
                        ->orWhere('medical_record_number', 'like', '%'.$search.'%');
                });
            })
            ->orderBy('full_name')
            ->limit(100)
            ->get();

        return view('staff.inpatient-admissions.create', [
            'patients' => $patients,
            'search' => $search,
            'selectedPatientId' => $validated['patient_id'] ?? null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'patient_id' => ['required', 'integer', 'exists:patient_profiles,id'],
            'room_number' => ['nullable', 'string', 'max:30'],
            'bed_number' => ['nullable', 'string', 'max:30'],
            'admission_reason' => ['required', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($request, $validated): void {
            $patient = DB::table('patient_profiles')
                ->where('id', $validated['patient_id'])
                ->lockForUpdate()
                ->first();

            if ($patient === null) {
                throw ValidationException::withMessages([
                    'patient_id' => 'Pasien tidak ditemukan.',
                ]);
            }

            $alreadyAdmitted = DB::table('inpatient_admissions')
                ->where('patient_id', $patient->id)
                ->whereNull('discharged_at')
                ->exists();

            if ($alreadyAdmitted) {
                throw ValidationException::withMessages([
                    'patient_id' => 'Pasien ini masih tercatat menjalani rawat inap.',
                ]);
            }

            DB::table('inpatient_admissions')->insert([
                'patient_id' => $patient->id,
                'room_number' => $validated['room_number'] ?? null,
                'bed_number' => $validated['bed_number'] ?? null,
                'admission_reason' => $validated['admission_reason'],
                'admitted_by_user_id' => $request->user()->id,
                'admitted_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        return redirect()
            ->route('staff.inpatient-admissions.index')
            ->with('success', 'Pasien berhasil dicatat sebagai pasien rawat inap.');
    }

    public function discharge(Request $request, int $admission): RedirectResponse
    {
        $updated = DB::table('inpatient_admissions')
            ->where('id', $admission)
            ->whereNull('discharged_at')
            ->update([
                'discharged_by_user_id' => $request->user()->id,
                'discharged_at' => now(),
                'updated_at' => now(),
            ]);

        abort_if($updated === 0, 404, 'Data rawat inap aktif tidak ditemukan.');

        return redirect()
            ->route('staff.inpatient-admissions.index')
            ->with('success', 'Pasien berhasil dicatat keluar dari rawat inap.');
    }
}
