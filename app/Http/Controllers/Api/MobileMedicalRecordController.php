<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\MobilePatientAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MobileMedicalRecordController extends Controller
{
    public function index(
        Request $request,
        int $patient,
        MobilePatientAccess $patientAccess,
    ): JsonResponse {
        $validated = $request->validate([
            'per_page' => ['nullable', 'integer', 'between:1,100'],
        ]);

        /** @var User $user */
        $user = $request->user();
        abort_unless($patientAccess->patientIds($user)->contains($patient), JsonResponse::HTTP_NOT_FOUND);

        $patientProfile = DB::table('patient_profiles')
            ->where('id', $patient)
            ->select(['id', 'medical_record_number', 'full_name'])
            ->first();

        abort_if($patientProfile === null, JsonResponse::HTTP_NOT_FOUND);

        $consultations = DB::table('appointments as appointments')
            ->join('doctor_consultations as consultations', 'consultations.appointment_id', '=', 'appointments.id')
            ->join('doctor_schedules as schedules', 'schedules.id', '=', 'appointments.doctor_schedule_id')
            ->join('doctors', 'doctors.id', '=', 'schedules.doctor_id')
            ->join('departments', 'departments.id', '=', 'doctors.department_id')
            ->where('appointments.patient_id', $patient)
            ->where('appointments.status', 'Completed')
            ->selectRaw("'consultation' AS record_type")
            ->addSelect([
                'appointments.id',
                'appointments.appointment_date as occurred_at',
                'appointments.status',
                'appointments.booking_code',
                'appointments.consultation_type',
                'appointments.initial_complaint',
                'doctors.full_name as doctor_name',
                'departments.name as department_name',
                'consultations.diagnosis',
                'consultations.treatment_plan',
                'consultations.notes',
            ])
            ->selectRaw('NULL AS admission_reason')
            ->selectRaw('NULL AS room_number')
            ->selectRaw('NULL AS bed_number')
            ->selectRaw('NULL AS admitted_at')
            ->selectRaw('NULL AS discharged_at')
            ->selectRaw('NULL AS inpatient_admission_id');

        $admissions = DB::table('inpatient_admissions as admissions')
            ->where('admissions.patient_id', $patient)
            ->selectRaw("'inpatient_admission' AS record_type")
            ->addSelect([
                'admissions.id',
                'admissions.admitted_at as occurred_at',
            ])
            ->selectRaw("CASE WHEN admissions.discharged_at IS NULL THEN 'Admitted' ELSE 'Discharged' END AS status")
            ->selectRaw('NULL AS booking_code')
            ->selectRaw('NULL AS consultation_type')
            ->selectRaw('NULL AS initial_complaint')
            ->selectRaw('NULL AS doctor_name')
            ->selectRaw('NULL AS department_name')
            ->selectRaw('NULL AS diagnosis')
            ->selectRaw('NULL AS treatment_plan')
            ->selectRaw('NULL AS notes')
            ->addSelect([
                'admissions.admission_reason',
                'admissions.room_number',
                'admissions.bed_number',
                'admissions.admitted_at',
                'admissions.discharged_at',
                'admissions.id as inpatient_admission_id',
            ]);

        $records = DB::query()
            ->fromSub($consultations->unionAll($admissions), 'medical_records')
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->orderBy('record_type')
            ->paginate((int) ($validated['per_page'] ?? 20));

        $admissionIds = $records->getCollection()
            ->where('record_type', 'inpatient_admission')
            ->pluck('inpatient_admission_id');

        $careUpdates = DB::table('patient_care_updates')
            ->whereIn('inpatient_admission_id', $admissionIds)
            ->select([
                'id',
                'inpatient_admission_id',
                'update_status',
                'temperature_celsius',
                'blood_pressure',
                'pulse_rate',
                'respiratory_rate',
                'oxygen_saturation',
                'notes',
                'created_at',
            ])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get()
            ->groupBy('inpatient_admission_id');

        $records->getCollection()->transform(static function (object $record) use ($careUpdates): array {
            $admissionId = $record->inpatient_admission_id;
            unset($record->inpatient_admission_id);

            $record->care_updates = $admissionId === null
                ? []
                : ($careUpdates->get($admissionId, collect())->values());

            return (array) $record;
        });

        return response()->json([
            'data' => [
                'patient' => $patientProfile,
                'records' => $records,
            ],
        ]);
    }
}
