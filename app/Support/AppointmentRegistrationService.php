<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AppointmentRegistrationService
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

    public function create(
        int $patientId,
        int $scheduleId,
        string $date,
        ?string $initialComplaint,
        string $consultationType,
    ): int {
        return DB::transaction(function () use ($patientId, $scheduleId, $date, $initialComplaint, $consultationType): int {
            $schedule = DB::table('doctor_schedules')
                ->where('id', $scheduleId)
                ->lockForUpdate()
                ->first();

            $dayOfWeek = self::DAYS_OF_WEEK[Carbon::parse($date)->dayOfWeekIso];

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
                ->whereDate('appointment_date', $date)
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

            return DB::table('appointments')->insertGetId([
                'booking_code' => $this->generateBookingCode($date),
                'patient_id' => $patientId,
                'doctor_schedule_id' => $schedule->id,
                'appointment_date' => $date,
                'queue_number' => $queueNumber,
                'initial_complaint' => $initialComplaint ?: null,
                'consultation_type' => $consultationType,
                'status' => 'Pending',
            ]);
        });
    }

    private function generateBookingCode(string $date): string
    {
        do {
            $bookingCode = 'REG-'.str_replace('-', '', $date).'-'.Str::upper(Str::random(6));
        } while (DB::table('appointments')->where('booking_code', $bookingCode)->exists());

        return $bookingCode;
    }
}
