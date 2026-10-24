<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MedicalRecordController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $search = trim((string) ($validated['search'] ?? ''));

        $patients = DB::table('patient_profiles')
            ->select(['id', 'medical_record_number', 'full_name', 'national_id', 'phone_number', 'gender'])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('full_name', 'like', '%'.$search.'%')
                        ->orWhere('medical_record_number', 'like', '%'.$search.'%')
                        ->orWhere('national_id', 'like', '%'.$search.'%')
                        ->orWhere('phone_number', 'like', '%'.$search.'%');
                });
            })
            ->orderBy('full_name')
            ->paginate(15)
            ->withQueryString();

        return view('staff.medical-records.index', compact('patients', 'search'));
    }

    public function show(int $patient): View
    {
        $patientRecord = DB::table('patient_profiles')
            ->where('id', $patient)
            ->first();

        abort_if($patientRecord === null, 404, 'Pasien tidak ditemukan.');

        $query = DB::table('appointments as appointments')
            ->join('doctor_schedules as schedules', 'schedules.id', '=', 'appointments.doctor_schedule_id')
            ->join('doctors', 'doctors.id', '=', 'schedules.doctor_id')
            ->join('departments', 'departments.id', '=', 'doctors.department_id')
            ->where('appointments.patient_id', $patient)
            ->select([
                'appointments.id',
                'appointments.booking_code',
                'appointments.appointment_date',
                'appointments.queue_number',
                'appointments.status',
                'appointments.initial_complaint',
                'appointments.consultation_type',
                'doctors.full_name as doctor_name',
                'departments.name as department_name',
                'schedules.start_time',
            ]);

        if (Schema::hasTable('doctor_consultations')) {
            $query->leftJoin('doctor_consultations as consultations', 'consultations.appointment_id', '=', 'appointments.id')
                ->leftJoin('departments as referral_departments', 'referral_departments.id', '=', 'consultations.referral_department_id')
                ->addSelect([
                    'consultations.diagnosis',
                    'consultations.treatment_plan',
                    'consultations.disposition',
                    'referral_departments.name as referral_department_name',
                    'consultations.notes',
                    'consultations.created_at as consultation_created_at',
                ]);
        }

        $records = $query
            ->orderByDesc('appointments.appointment_date')
            ->orderByDesc('appointments.id')
            ->get();

        return view('staff.medical-records.show', [
            'patient' => $patientRecord,
            'records' => $records,
        ]);
    }
}
