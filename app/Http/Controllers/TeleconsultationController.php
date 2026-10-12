<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TeleconsultationController extends Controller
{
    public function index(Request $request): View
    {
        $roleName = $this->roleName($request);
        abort_unless(in_array($roleName, ['Dokter', 'Pasien'], true), 403);

        $appointments = $this->participantAppointments($request, $roleName)
            ->select([
                'appointments.id',
                'appointments.booking_code',
                'appointments.appointment_date',
                'appointments.queue_number',
                'appointments.status',
                'patients.full_name as patient_name',
                'doctors.full_name as doctor_name',
                'departments.name as department_name',
            ])
            ->addSelect([
                'latest_message' => DB::table('teleconsultation_messages')
                    ->select('message')
                    ->whereColumn('appointment_id', 'appointments.id')
                    ->orderByDesc('id')
                    ->limit(1),
            ])
            ->addSelect([
                'latest_message_at' => DB::table('teleconsultation_messages')
                    ->select('created_at')
                    ->whereColumn('appointment_id', 'appointments.id')
                    ->orderByDesc('id')
                    ->limit(1),
            ])
            ->orderByDesc('appointments.appointment_date')
            ->orderBy('appointments.queue_number')
            ->get();

        $routePrefix = $roleName === 'Dokter'
            ? 'staff.doctor.teleconsultations'
            : 'patient.teleconsultations';

        return view('teleconsultations.index', compact('appointments', 'routePrefix', 'roleName'));
    }

    public function show(Request $request, int $appointment): View
    {
        $roleName = $this->roleName($request);
        abort_unless(in_array($roleName, ['Dokter', 'Pasien'], true), 403);

        $record = $this->participantAppointments($request, $roleName)
            ->where('appointments.id', $appointment)
            ->select([
                'appointments.id',
                'appointments.booking_code',
                'appointments.appointment_date',
                'appointments.queue_number',
                'appointments.status',
                'appointments.initial_complaint',
                'patients.full_name as patient_name',
                'patients.medical_record_number',
                'doctors.full_name as doctor_name',
                'departments.name as department_name',
            ])
            ->first();

        abort_if($record === null, 404);

        $messages = DB::table('teleconsultation_messages as messages')
            ->join('users', 'users.id', '=', 'messages.sender_user_id')
            ->join('roles', 'roles.id', '=', 'users.role_id')
            ->leftJoin('doctors', 'doctors.user_id', '=', 'users.id')
            ->leftJoin('patient_profiles as sender_patients', 'sender_patients.user_id', '=', 'users.id')
            ->where('messages.appointment_id', $appointment)
            ->select([
                'messages.id',
                'messages.sender_user_id',
                'messages.message',
                'messages.created_at',
                'roles.role_name as sender_role',
                'doctors.full_name as doctor_sender_name',
                'sender_patients.full_name as patient_sender_name',
            ])
            ->orderBy('messages.id')
            ->get();

        $routePrefix = $roleName === 'Dokter'
            ? 'staff.doctor.teleconsultations'
            : 'patient.teleconsultations';
        $canSendMessages = in_array($record->status, ['Pending', 'Confirmed', 'Checked-In'], true);
        $isDoctor = $roleName === 'Dokter';

        return view('teleconsultations.show', compact(
            'record',
            'messages',
            'routePrefix',
            'canSendMessages',
            'isDoctor',
        ));
    }

    public function sendMessage(Request $request, int $appointment): RedirectResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:3000'],
        ]);
        $roleName = $this->roleName($request);
        abort_unless(in_array($roleName, ['Dokter', 'Pasien'], true), 403);
        $message = trim($validated['message']);

        if ($message === '') {
            throw ValidationException::withMessages([
                'message' => 'Isi pesan tidak boleh kosong.',
            ]);
        }

        DB::transaction(function () use ($request, $roleName, $appointment, $message): void {
            $record = $this->participantAppointments($request, $roleName)
                ->where('appointments.id', $appointment)
                ->lockForUpdate()
                ->select(['appointments.id', 'appointments.status'])
                ->first();

            abort_if($record === null, 404);

            if (! in_array($record->status, ['Pending', 'Confirmed', 'Checked-In'], true)) {
                throw ValidationException::withMessages([
                    'message' => 'Ruang chat hanya tersedia selama telekonsultasi masih aktif.',
                ]);
            }

            DB::table('teleconsultation_messages')->insert([
                'appointment_id' => $record->id,
                'sender_user_id' => $request->user()->id,
                'message' => $message,
                'created_at' => now(),
            ]);
        });

        $routeName = $roleName === 'Dokter'
            ? 'staff.doctor.teleconsultations.show'
            : 'patient.teleconsultations.show';

        return redirect()->route($routeName, $appointment);
    }

    public function complete(Request $request, int $appointment): RedirectResponse
    {
        abort_unless($this->roleName($request) === 'Dokter', 403);

        DB::transaction(function () use ($request, $appointment): void {
            $record = $this->participantAppointments($request, 'Dokter')
                ->where('appointments.id', $appointment)
                ->lockForUpdate()
                ->select(['appointments.id', 'appointments.status'])
                ->first();

            abort_if($record === null, 404);

            if (! in_array($record->status, ['Pending', 'Confirmed', 'Checked-In'], true)) {
                throw ValidationException::withMessages([
                    'appointment' => 'Telekonsultasi ini sudah ditutup atau dibatalkan.',
                ]);
            }

            DB::table('appointments')
                ->where('id', $record->id)
                ->update(['status' => 'Completed']);
        });

        return redirect()
            ->route('staff.doctor.teleconsultations.show', $appointment)
            ->with('success', 'Telekonsultasi telah diselesaikan. Riwayat chat tetap dapat dibaca.');
    }

    private function roleName(Request $request): string
    {
        return (string) DB::table('roles')
            ->where('id', $request->user()->role_id)
            ->value('role_name');
    }

    private function participantAppointments(Request $request, string $roleName): \Illuminate\Database\Query\Builder
    {
        $query = DB::table('appointments')
            ->join('doctor_schedules as schedules', 'schedules.id', '=', 'appointments.doctor_schedule_id')
            ->join('doctors', 'doctors.id', '=', 'schedules.doctor_id')
            ->join('departments', 'departments.id', '=', 'doctors.department_id')
            ->join('patient_profiles as patients', 'patients.id', '=', 'appointments.patient_id')
            ->where('appointments.consultation_type', 'Teleconsultation')
            ->where('appointments.status', '!=', 'Cancelled');

        if ($roleName === 'Dokter') {
            return $query->where('doctors.user_id', $request->user()->id);
        }

        abort_unless($roleName === 'Pasien', 403);

        return $query->where('patients.user_id', $request->user()->id);
    }
}
