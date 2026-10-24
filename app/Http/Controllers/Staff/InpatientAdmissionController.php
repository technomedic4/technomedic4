<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
            ->leftJoin('appointments', 'appointments.id', '=', 'admissions.appointment_id')
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
                'appointments.booking_code',
                'patients.full_name as patient_name',
                'patients.medical_record_number',
                'admitted_by.email as admitted_by',
            ])
            ->orderByDesc('admissions.admitted_at')
            ->paginate(15)
            ->withQueryString();

        return view('staff.inpatient-admissions.index', compact('admissions', 'search'));
    }

    public function assignRoom(Request $request, int $admission): RedirectResponse
    {
        $validated = $request->validate([
            'room_number' => ['required', 'string', 'max:30'],
            'bed_number' => ['required', 'string', 'max:30'],
        ]);

        $updated = DB::table('inpatient_admissions')
            ->where('id', $admission)
            ->whereNull('discharged_at')
            ->update([
                'room_number' => $validated['room_number'],
                'bed_number' => $validated['bed_number'],
                'updated_at' => now(),
            ]);

        abort_if($updated === 0, 404, 'Data rawat inap aktif tidak ditemukan.');

        return redirect()
            ->route('staff.inpatient-admissions.index')
            ->with('success', 'Kamar dan tempat tidur pasien berhasil diperbarui.');
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
