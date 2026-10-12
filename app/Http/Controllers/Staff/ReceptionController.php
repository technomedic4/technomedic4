<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ReceptionController extends Controller
{
    private const DAYS_OF_WEEK = [
        1 => 'Senin',
        2 => 'Selasa',
        3 => 'Rabu',
        4 => 'Kamis',
        5 => 'Jumat',
        6 => 'Sabtu',
        7 => 'Minggu',
    ];

    public function patients(Request $request): View
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $search = trim($validated['search'] ?? '');

        $patients = DB::table('patient_profiles')
            ->select(['id', 'medical_record_number', 'national_id', 'full_name', 'phone_number', 'gender'])
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

        return view('staff.reception.patients.index', compact('patients', 'search'));
    }

    public function createPatient(Request $request): View
    {
        return view('staff.reception.patients.create', [
            'forRegistration' => $request->boolean('for_registration'),
        ]);
    }

    public function storePatient(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'full_name' => ['required', 'string', 'max:150'],
            'national_id' => ['nullable', 'digits:16', Rule::unique('patient_profiles', 'national_id')],
            'phone_number' => ['required', 'string', 'max:20'],
            'emergency_contact' => ['nullable', 'string', 'max:20'],
            'gender' => ['required', Rule::in(['L', 'P'])],
            'birth_date' => ['required', 'date', 'before_or_equal:today'],
            'address' => ['nullable', 'string', 'max:2000'],
            'for_registration' => ['nullable', 'boolean'],
        ]);

        $patientId = DB::transaction(function () use ($validated): int {
            $patientId = DB::table('patient_profiles')->insertGetId([
                'user_id' => null,
                'full_name' => $validated['full_name'],
                'national_id' => $validated['national_id'] ?: null,
                'phone_number' => $validated['phone_number'],
                'emergency_contact' => $validated['emergency_contact'] ?: null,
                'gender' => $validated['gender'],
                'birth_date' => $validated['birth_date'],
                'address' => $validated['address'] ?: null,
            ]);

            DB::table('patient_profiles')
                ->where('id', $patientId)
                ->update([
                    'medical_record_number' => 'RM-'.str_pad((string) $patientId, 8, '0', STR_PAD_LEFT),
                ]);

            return $patientId;
        });

        if ($request->boolean('for_registration')) {
            return redirect()
                ->route('staff.reception.registrations.create', ['patient_id' => $patientId])
                ->with('success', 'Data pasien tersimpan. Lengkapi pendaftaran kunjungan.');
        }

        return redirect()
            ->route('staff.reception.patients.index')
            ->with('success', 'Pasien berhasil didaftarkan.');
    }

    public function registrations(Request $request): View
    {
        $validated = $request->validate([
            'date' => ['nullable', 'date'],
            'status' => ['nullable', Rule::in(['Pending', 'Confirmed', 'Checked-In', 'Completed', 'Cancelled'])],
        ]);
        $date = $validated['date'] ?? now()->toDateString();

        $appointments = DB::table('appointments')
            ->join('patient_profiles', 'patient_profiles.id', '=', 'appointments.patient_id')
            ->join('doctor_schedules', 'doctor_schedules.id', '=', 'appointments.doctor_schedule_id')
            ->join('doctors', 'doctors.id', '=', 'doctor_schedules.doctor_id')
            ->join('departments', 'departments.id', '=', 'doctors.department_id')
            ->whereDate('appointments.appointment_date', $date)
            ->when(
                isset($validated['status']),
                fn ($query) => $query->where('appointments.status', $validated['status']),
            )
            ->select([
                'appointments.id',
                'appointments.booking_code',
                'appointments.appointment_date',
                'appointments.queue_number',
                'appointments.status',
                'appointments.consultation_type',
                'appointments.initial_complaint',
                'patient_profiles.full_name as patient_name',
                'patient_profiles.medical_record_number',
                'doctors.full_name as doctor_name',
                'departments.name as department_name',
                'doctor_schedules.start_time',
            ])
            ->orderBy('departments.name')
            ->orderBy('appointments.queue_number')
            ->paginate(20)
            ->withQueryString();

        return view('staff.reception.registrations.index', [
            'appointments' => $appointments,
            'date' => $date,
            'status' => $validated['status'] ?? '',
        ]);
    }

    public function createRegistration(Request $request): View|RedirectResponse
    {
        $validated = $request->validate([
            'patient_id' => ['required', 'integer', 'exists:patient_profiles,id'],
            'date' => ['nullable', 'date', 'after_or_equal:today'],
        ]);

        $patient = DB::table('patient_profiles')
            ->select(['id', 'medical_record_number', 'full_name', 'national_id', 'phone_number'])
            ->find($validated['patient_id']);

        if ($patient === null) {
            return redirect()
                ->route('staff.reception.patients.index')
                ->withErrors(['patient_id' => 'Pasien tidak ditemukan.']);
        }

        $date = $validated['date'] ?? now()->toDateString();
        $schedules = $this->availableSchedules();

        return view('staff.reception.registrations.create', [
            'date' => $date,
            'patient' => $patient,
            'schedules' => $schedules,
        ]);
    }

    public function storeRegistration(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'patient_id' => ['required', 'integer', 'exists:patient_profiles,id'],
            'doctor_schedule_id' => ['required', 'integer', 'exists:doctor_schedules,id'],
            'appointment_date' => ['required', 'date', 'after_or_equal:today'],
            'initial_complaint' => ['nullable', 'string', 'max:2000'],
            'consultation_type' => ['nullable', Rule::in(['In-Person', 'Teleconsultation'])],
        ]);

        $appointmentId = DB::transaction(function () use ($validated): int {
            $schedule = DB::table('doctor_schedules')
                ->where('id', $validated['doctor_schedule_id'])
                ->lockForUpdate()
                ->first();

            $dayOfWeek = self::DAYS_OF_WEEK[Carbon::parse($validated['appointment_date'])->dayOfWeekIso];

            if ($schedule === null || $schedule->day_of_week !== $dayOfWeek) {
                throw ValidationException::withMessages([
                    'doctor_schedule_id' => 'Jadwal dokter tidak sesuai dengan hari kunjungan yang dipilih.',
                ]);
            }

            if ((int) $schedule->quota_limit < 1) {
                throw ValidationException::withMessages([
                    'doctor_schedule_id' => 'Kuota jadwal dokter belum tersedia untuk pendaftaran.',
                ]);
            }

            $activeAppointments = DB::table('appointments')
                ->where('doctor_schedule_id', $schedule->id)
                ->whereDate('appointment_date', $validated['appointment_date'])
                ->where('status', '!=', 'Cancelled')
                ->pluck('queue_number')
                ->map(static fn ($queueNumber): int => (int) $queueNumber)
                ->all();

            $availableQueueNumbers = array_values(array_diff(
                range(1, (int) $schedule->quota_limit),
                $activeAppointments,
            ));
            $queueNumber = $availableQueueNumbers[0] ?? null;

            if ($queueNumber === null) {
                throw ValidationException::withMessages([
                    'doctor_schedule_id' => 'Kuota jadwal dokter untuk tanggal tersebut sudah penuh.',
                ]);
            }

            $bookingCode = $this->generateBookingCode($validated['appointment_date']);

            return DB::table('appointments')->insertGetId([
                'booking_code' => $bookingCode,
                'patient_id' => $validated['patient_id'],
                'doctor_schedule_id' => $schedule->id,
                'appointment_date' => $validated['appointment_date'],
                'queue_number' => $queueNumber,
                'initial_complaint' => ($validated['initial_complaint'] ?? null) ?: null,
                'consultation_type' => $validated['consultation_type'] ?? 'In-Person',
                'status' => 'Pending',
            ]);
        });

        return redirect()
            ->route('staff.reception.registrations.index', ['date' => $validated['appointment_date']])
            ->with('success', 'Pendaftaran berhasil. Nomor antrean '.$this->queueLabel($appointmentId).'.');
    }

    public function checkIn(Request $request, int $appointment): RedirectResponse
    {
        DB::transaction(function () use ($request, $appointment): void {
            $record = DB::table('appointments')
                ->where('id', $appointment)
                ->lockForUpdate()
                ->first();

            if ($record === null) {
                abort(404);
            }

            if (! in_array($record->status, ['Pending', 'Confirmed'], true)) {
                throw ValidationException::withMessages([
                    'appointment' => 'Hanya pendaftaran yang menunggu atau telah dikonfirmasi yang dapat check-in.',
                ]);
            }

            DB::table('appointments')
                ->where('id', $appointment)
                ->update(['status' => 'Checked-In']);

            DB::table('check_in_logs')->insert([
                'appointment_id' => $appointment,
                'verified_by_user_id' => $request->user()->id,
            ]);
        });

        return back()->with('success', 'Pasien berhasil check-in.');
    }

    public function cancelRegistration(int $appointment): RedirectResponse
    {
        $updated = DB::table('appointments')
            ->where('id', $appointment)
            ->whereIn('status', ['Pending', 'Confirmed'])
            ->update(['status' => 'Cancelled']);

        if ($updated === 0) {
            $exists = DB::table('appointments')->where('id', $appointment)->exists();

            if (! $exists) {
                abort(404);
            }

            throw ValidationException::withMessages([
                'appointment' => 'Pendaftaran yang sudah check-in atau selesai tidak dapat dibatalkan dari loket.',
            ]);
        }

        return back()->with('success', 'Pendaftaran berhasil dibatalkan.');
    }

    private function availableSchedules(): \Illuminate\Support\Collection
    {
        return DB::table('doctor_schedules')
            ->join('doctors', 'doctors.id', '=', 'doctor_schedules.doctor_id')
            ->join('departments', 'departments.id', '=', 'doctors.department_id')
            ->select([
                'doctor_schedules.id',
                'doctor_schedules.day_of_week',
                'doctor_schedules.start_time',
                'doctor_schedules.end_time',
                'doctor_schedules.quota_limit',
                'doctors.full_name as doctor_name',
                'departments.name as department_name',
            ])
            ->orderBy('departments.name')
            ->orderBy('doctors.full_name')
            ->orderByRaw(
                "CASE doctor_schedules.day_of_week
                    WHEN 'Senin' THEN 1
                    WHEN 'Selasa' THEN 2
                    WHEN 'Rabu' THEN 3
                    WHEN 'Kamis' THEN 4
                    WHEN 'Jumat' THEN 5
                    WHEN 'Sabtu' THEN 6
                    WHEN 'Minggu' THEN 7
                    ELSE 8
                END"
            )
            ->orderBy('doctor_schedules.start_time')
            ->get();
    }

    private function generateBookingCode(string $date): string
    {
        do {
            $bookingCode = 'REG-'.str_replace('-', '', $date).'-'.Str::upper(Str::random(6));
        } while (DB::table('appointments')->where('booking_code', $bookingCode)->exists());

        return $bookingCode;
    }

    private function queueLabel(int $appointmentId): string
    {
        $queueNumber = DB::table('appointments')->where('id', $appointmentId)->value('queue_number');

        return str_pad((string) $queueNumber, 3, '0', STR_PAD_LEFT);
    }
}
