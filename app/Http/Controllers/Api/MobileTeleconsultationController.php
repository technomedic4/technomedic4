<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\MobilePatientAccess;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MobileTeleconsultationController extends Controller
{
    public function index(Request $request, MobilePatientAccess $patientAccess): JsonResponse
    {
        $validated = $request->validate([
            'per_page' => ['nullable', 'integer', 'between:1,100'],
        ]);

        /** @var User $user */
        $user = $request->user();

        $teleconsultations = $this->teleconsultationQuery($patientAccess->patientIds($user))
            ->orderByDesc('appointments.appointment_date')
            ->orderBy('appointments.queue_number')
            ->paginate((int) ($validated['per_page'] ?? 20));

        return response()->json(['data' => $teleconsultations]);
    }

    public function messages(
        Request $request,
        int $appointment,
        MobilePatientAccess $patientAccess,
    ): JsonResponse {
        $record = $this->findAccessibleTeleconsultation($request, $appointment, $patientAccess);
        $validated = $request->validate([
            'per_page' => ['nullable', 'integer', 'between:1,100'],
        ]);

        $messages = DB::table('teleconsultation_messages as messages')
            ->join('users', 'users.id', '=', 'messages.sender_user_id')
            ->join('roles', 'roles.id', '=', 'users.role_id')
            ->leftJoin('doctors', 'doctors.user_id', '=', 'users.id')
            ->leftJoin('patient_profiles as sender_patients', 'sender_patients.user_id', '=', 'users.id')
            ->where('messages.appointment_id', $record->id)
            ->select([
                'messages.id',
                'messages.sender_user_id',
                'messages.message',
                'messages.created_at',
                'roles.role_name as sender_role',
            ])
            ->selectRaw(
                'COALESCE(doctors.full_name, sender_patients.full_name, users.email) AS sender_name',
            )
            ->orderBy('messages.id')
            ->paginate((int) ($validated['per_page'] ?? 50));

        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'data' => [
                'appointment' => [
                    'id' => $record->id,
                    'booking_code' => $record->booking_code,
                    'patient_id' => $record->patient_id,
                    'patient_name' => $record->patient_name,
                    'doctor_name' => $record->doctor_name,
                    'department_name' => $record->department_name,
                    'status' => $record->status,
                ],
                'can_send_messages' => in_array($record->status, ['Pending', 'Confirmed', 'Checked-In'], true)
                    && $patientAccess->canManage($user, (int) $record->patient_id),
                'messages' => $messages,
            ],
        ]);
    }

    public function sendMessage(
        Request $request,
        int $appointment,
        MobilePatientAccess $patientAccess,
    ): JsonResponse {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:3000'],
        ]);
        $message = trim($validated['message']);

        if ($message === '') {
            throw ValidationException::withMessages([
                'message' => 'Isi pesan tidak boleh kosong.',
            ]);
        }

        /** @var User $user */
        $user = $request->user();

        DB::transaction(function () use ($user, $appointment, $patientAccess, $message): void {
            $record = $this->teleconsultationQuery($patientAccess->patientIds($user))
                ->where('appointments.id', $appointment)
                ->lockForUpdate()
                ->select([
                    'appointments.id',
                    'appointments.patient_id',
                    'appointments.status',
                ])
                ->first();

            abort_if($record === null, JsonResponse::HTTP_NOT_FOUND);

            if (! $patientAccess->canManage($user, (int) $record->patient_id)) {
                abort(JsonResponse::HTTP_FORBIDDEN, 'Akun keluarga hanya dapat mengirim pesan dengan izin Full-Access.');
            }

            if (! in_array($record->status, ['Pending', 'Confirmed', 'Checked-In'], true)) {
                throw ValidationException::withMessages([
                    'message' => 'Ruang chat hanya tersedia selama telekonsultasi masih aktif.',
                ]);
            }

            DB::table('teleconsultation_messages')->insert([
                'appointment_id' => $record->id,
                'sender_user_id' => $user->id,
                'message' => $message,
                'created_at' => now(),
            ]);
        });

        return response()->json(['message' => 'Pesan berhasil dikirim.'], JsonResponse::HTTP_CREATED);
    }

    private function findAccessibleTeleconsultation(
        Request $request,
        int $appointment,
        MobilePatientAccess $patientAccess,
    ): object {
        /** @var User $user */
        $user = $request->user();

        return $this->teleconsultationQuery($patientAccess->patientIds($user))
            ->where('appointments.id', $appointment)
            ->firstOrFail();
    }

    /**
     * @param  Collection<int, int>  $patientIds
     */
    private function teleconsultationQuery(Collection $patientIds): Builder
    {
        return DB::table('appointments')
            ->join('patient_profiles', 'patient_profiles.id', '=', 'appointments.patient_id')
            ->join('doctor_schedules', 'doctor_schedules.id', '=', 'appointments.doctor_schedule_id')
            ->join('doctors', 'doctors.id', '=', 'doctor_schedules.doctor_id')
            ->join('departments', 'departments.id', '=', 'doctors.department_id')
            ->whereIn('appointments.patient_id', $patientIds)
            ->where('appointments.consultation_type', 'Teleconsultation')
            ->where('appointments.status', '!=', 'Cancelled')
            ->select([
                'appointments.id',
                'appointments.booking_code',
                'appointments.patient_id',
                'patient_profiles.full_name as patient_name',
                'appointments.appointment_date',
                'appointments.queue_number',
                'appointments.status',
                'doctors.full_name as doctor_name',
                'departments.name as department_name',
            ]);
    }
}
