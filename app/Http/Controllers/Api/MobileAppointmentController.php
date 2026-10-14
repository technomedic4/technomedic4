<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\AppointmentRegistrationService;
use App\Support\MobilePatientAccess;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class MobileAppointmentController extends Controller
{
    public function schedules(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date' => ['nullable', 'date', 'after_or_equal:today'],
        ]);
        $date = $validated['date'] ?? now()->toDateString();
        $dayOfWeek = [
            1 => 'Senin',
            2 => 'Selasa',
            3 => 'Rabu',
            4 => 'Kamis',
            5 => 'Jumat',
            6 => 'Sabtu',
            7 => 'Minggu',
        ][Carbon::parse($date)->dayOfWeekIso];

        $schedules = DB::table('doctor_schedules')
            ->join('doctors', 'doctors.id', '=', 'doctor_schedules.doctor_id')
            ->join('departments', 'departments.id', '=', 'doctors.department_id')
            ->where('doctor_schedules.day_of_week', $dayOfWeek)
            ->where('doctor_schedules.quota_limit', '>', 0)
            ->select([
                'doctor_schedules.id',
                'doctor_schedules.day_of_week',
                'doctor_schedules.start_time',
                'doctor_schedules.end_time',
                'doctor_schedules.quota_limit',
                'doctors.full_name as doctor_name',
                'departments.name as department_name',
            ])
            ->addSelect([
                'booked_slots' => DB::table('appointments')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('appointments.doctor_schedule_id', 'doctor_schedules.id')
                    ->whereDate('appointments.appointment_date', $date)
                    ->where('appointments.status', '!=', 'Cancelled'),
            ])
            ->orderBy('departments.name')
            ->orderBy('doctors.full_name')
            ->orderBy('doctor_schedules.start_time')
            ->get()
            ->map(static function (object $schedule): array {
                $schedule->remaining_slots = max(0, (int) $schedule->quota_limit - (int) $schedule->booked_slots);

                return (array) $schedule;
            })
            ->filter(static fn (array $schedule): bool => $schedule['remaining_slots'] > 0)
            ->values();

        return response()->json([
            'data' => [
                'date' => $date,
                'schedules' => $schedules,
            ],
        ]);
    }

    public function index(Request $request, MobilePatientAccess $patientAccess): JsonResponse
    {
        $validated = $request->validate([
            'per_page' => ['nullable', 'integer', 'between:1,100'],
        ]);

        /** @var User $user */
        $user = $request->user();

        $appointments = $this->appointmentQuery($patientAccess->patientIds($user))
            ->orderByDesc('appointments.appointment_date')
            ->orderBy('appointments.queue_number')
            ->paginate((int) ($validated['per_page'] ?? 20));

        return response()->json(['data' => $appointments]);
    }

    public function store(
        Request $request,
        MobilePatientAccess $patientAccess,
        AppointmentRegistrationService $appointmentRegistration,
    ): JsonResponse {
        $validated = $request->validate([
            'patient_id' => ['required', 'integer', 'min:1'],
            'doctor_schedule_id' => ['required', 'integer', 'exists:doctor_schedules,id'],
            'appointment_date' => ['required', 'date', 'after_or_equal:today'],
            'initial_complaint' => ['nullable', 'string', 'max:2000'],
            'consultation_type' => ['nullable', Rule::in(['In-Person', 'Teleconsultation'])],
        ]);

        /** @var User $user */
        $user = $request->user();
        $patientId = (int) $validated['patient_id'];

        abort_unless($patientAccess->canManage($user, $patientId), JsonResponse::HTTP_NOT_FOUND);

        $appointmentId = $appointmentRegistration->create(
            $patientId,
            (int) $validated['doctor_schedule_id'],
            $validated['appointment_date'],
            $validated['initial_complaint'] ?? null,
            $validated['consultation_type'] ?? 'In-Person',
        );

        $appointment = $this->appointmentQuery(collect([$patientId]))
            ->where('appointments.id', $appointmentId)
            ->firstOrFail();

        return response()->json(['data' => $appointment], JsonResponse::HTTP_CREATED);
    }

    /**
     * @param  Collection<int, int>  $patientIds
     */
    private function appointmentQuery(Collection $patientIds): Builder
    {
        return DB::table('appointments')
            ->join('patient_profiles', 'patient_profiles.id', '=', 'appointments.patient_id')
            ->join('doctor_schedules', 'doctor_schedules.id', '=', 'appointments.doctor_schedule_id')
            ->join('doctors', 'doctors.id', '=', 'doctor_schedules.doctor_id')
            ->join('departments', 'departments.id', '=', 'doctors.department_id')
            ->whereIn('appointments.patient_id', $patientIds)
            ->select([
                'appointments.id',
                'appointments.booking_code',
                'appointments.patient_id',
                'patient_profiles.full_name as patient_name',
                'appointments.appointment_date',
                'appointments.queue_number',
                'appointments.initial_complaint',
                'appointments.consultation_type',
                'appointments.status',
                'doctor_schedules.start_time',
                'doctor_schedules.end_time',
                'doctors.full_name as doctor_name',
                'departments.name as department_name',
            ]);
    }
}
