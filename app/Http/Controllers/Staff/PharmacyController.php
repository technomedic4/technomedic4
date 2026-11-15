<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PharmacyController extends Controller
{
    public function index(): View
    {
        $prescriptions = DB::table('prescriptions')
            ->join('bills', 'bills.id', '=', 'prescriptions.bill_id')
            ->join('patient_profiles as patients', 'patients.id', '=', 'prescriptions.patient_id')
            ->join('doctor_consultations as consultations', 'consultations.id', '=', 'prescriptions.consultation_id')
            ->join('doctors', 'doctors.id', '=', 'prescriptions.doctor_id')
            ->whereIn('bills.status', ['Unpaid', 'Partially Paid', 'Paid', 'Cancelled'])
            ->select([
                'prescriptions.id',
                'prescriptions.created_at',
                'prescriptions.dispensed_at',
                'bills.status as bill_status',
                'patients.full_name as patient_name',
                'patients.medical_record_number',
                'doctors.full_name as doctor_name',
                'consultations.diagnosis',
            ])
            ->orderByRaw('CASE WHEN prescriptions.dispensed_at IS NULL THEN 0 ELSE 1 END')
            ->orderByDesc('prescriptions.created_at')
            ->orderByDesc('prescriptions.id')
            ->paginate(20);

        $itemsByPrescriptionId = DB::table('prescription_items')
            ->whereIn('prescription_id', $prescriptions->getCollection()->pluck('id'))
            ->select([
                'prescription_id',
                'medicine_name',
                'strength',
                'dosage_form',
                'unit',
                'quantity',
                'dosage',
                'instructions',
            ])
            ->orderBy('id')
            ->get()
            ->groupBy('prescription_id');

        return view('staff.pharmacy.index', [
            'prescriptions' => $prescriptions,
            'itemsByPrescriptionId' => $itemsByPrescriptionId,
        ]);
    }

    public function dispense(Request $request, int $prescription): RedirectResponse
    {
        DB::transaction(function () use ($request, $prescription): void {
            $prescriptionRecord = DB::table('prescriptions')
                ->where('id', $prescription)
                ->lockForUpdate()
                ->first();

            abort_if($prescriptionRecord === null, 404);

            $bill = DB::table('bills')
                ->where('id', $prescriptionRecord->bill_id)
                ->lockForUpdate()
                ->first();

            abort_if($bill === null, 404);

            if ($bill->status !== 'Paid') {
                throw ValidationException::withMessages([
                    'prescription' => 'Obat hanya dapat diserahkan setelah keuangan mengonfirmasi tagihan lunas.',
                ]);
            }

            if ($prescriptionRecord->dispensed_at !== null) {
                throw ValidationException::withMessages([
                    'prescription' => 'Resep ini sudah dicatat sebagai diserahkan.',
                ]);
            }

            DB::table('prescriptions')
                ->where('id', $prescription)
                ->update([
                    'dispensed_by_user_id' => $request->user()->id,
                    'dispensed_at' => now(),
                    'updated_at' => now(),
                ]);
        });

        return redirect()
            ->route('staff.pharmacy.index')
            ->with('success', 'Resep ditandai sudah diserahkan kepada pasien.');
    }
}
