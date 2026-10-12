<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DoctorWorkflowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('roles', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('role_name')->unique();
        });

        Schema::create('users', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('role_id');
            $table->string('email')->unique();
            $table->string('phone_number')->nullable();
            $table->string('password_hash');
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('patient_profiles', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('medical_record_number')->nullable();
            $table->string('national_id')->nullable();
            $table->string('full_name');
            $table->string('phone_number')->nullable();
            $table->string('gender', 1)->nullable();
            $table->date('birth_date')->nullable();
            $table->text('address')->nullable();
        });

        Schema::create('departments', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('name');
        });

        Schema::create('doctors', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('department_id');
            $table->string('full_name');
        });

        Schema::create('doctor_schedules', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('doctor_id');
            $table->string('day_of_week');
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedInteger('quota_limit');
        });

        Schema::create('appointments', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('booking_code')->unique();
            $table->unsignedInteger('patient_id');
            $table->unsignedInteger('doctor_schedule_id');
            $table->date('appointment_date');
            $table->unsignedInteger('queue_number');
            $table->text('initial_complaint')->nullable();
            $table->string('consultation_type')->default('In-Person');
            $table->string('status');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('doctor_consultations', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('appointment_id')->unique();
            $table->unsignedInteger('patient_id');
            $table->unsignedInteger('doctor_id');
            $table->text('diagnosis');
            $table->text('treatment_plan');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function test_doctor_sees_only_their_own_daily_patients(): void
    {
        [$doctor, $scheduleId] = $this->createDoctor('Dr. Utama');
        [, $otherScheduleId] = $this->createDoctor('Dr. Lain');
        $ownAppointmentId = $this->createAppointment($scheduleId, 'Checked-In', 'Pasien Jadwal Saya');
        $otherAppointmentId = $this->createAppointment($otherScheduleId, 'Checked-In', 'Pasien Dokter Lain');

        $this->actingAs($doctor->user)
            ->get(route('staff.doctor.appointments.index'))
            ->assertOk()
            ->assertSeeText('Pasien Jadwal Saya')
            ->assertDontSeeText('Pasien Dokter Lain')
            ->assertSee(route('staff.doctor.appointments.show', $ownAppointmentId), false)
            ->assertDontSee(route('staff.doctor.appointments.show', $otherAppointmentId), false);
    }

    public function test_doctor_can_save_a_consultation_for_a_checked_in_patient_and_complete_visit(): void
    {
        [$doctor, $scheduleId] = $this->createDoctor('Dr. Utama');
        $appointmentId = $this->createAppointment($scheduleId, 'Checked-In', 'Pasien Konsultasi');

        $this->actingAs($doctor->user)
            ->post(route('staff.doctor.appointments.consultation.store', $appointmentId), [
                'diagnosis' => 'Infeksi saluran pernapasan atas',
                'treatment_plan' => 'Istirahat dan terapi sesuai pemeriksaan.',
                'notes' => 'Kontrol kembali jika keluhan menetap.',
            ])
            ->assertRedirect(route('staff.doctor.appointments.show', $appointmentId))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('doctor_consultations', [
            'appointment_id' => $appointmentId,
            'patient_id' => DB::table('appointments')->where('id', $appointmentId)->value('patient_id'),
            'doctor_id' => $doctor->id,
            'diagnosis' => 'Infeksi saluran pernapasan atas',
            'treatment_plan' => 'Istirahat dan terapi sesuai pemeriksaan.',
            'notes' => 'Kontrol kembali jika keluhan menetap.',
        ]);
        $this->assertDatabaseHas('appointments', [
            'id' => $appointmentId,
            'status' => 'Completed',
        ]);

        $this->get(route('staff.doctor.appointments.show', $appointmentId))
            ->assertOk()
            ->assertSeeText('Catatan konsultasi tersimpan')
            ->assertSeeText('Infeksi saluran pernapasan atas')
            ->assertSeeText('Istirahat dan terapi sesuai pemeriksaan.');

        $this->post(route('staff.doctor.appointments.consultation.store', $appointmentId), [
            'diagnosis' => 'Diagnosis kedua',
            'treatment_plan' => 'Rencana kedua',
        ])->assertSessionHasErrors('appointment');

        $this->assertDatabaseCount('doctor_consultations', 1);
    }

    public function test_doctor_cannot_open_or_record_a_patient_before_check_in(): void
    {
        [$doctor, $scheduleId] = $this->createDoctor('Dr. Utama');
        $appointmentId = $this->createAppointment($scheduleId, 'Pending', 'Pasien Belum Check-in');

        $this->actingAs($doctor->user)
            ->get(route('staff.doctor.appointments.show', $appointmentId))
            ->assertForbidden();

        $this->post(route('staff.doctor.appointments.consultation.store', $appointmentId), [
            'diagnosis' => 'Diagnosis uji',
            'treatment_plan' => 'Rencana terapi uji',
        ])->assertSessionHasErrors('appointment');

        $this->assertDatabaseCount('doctor_consultations', 0);
        $this->assertDatabaseHas('appointments', [
            'id' => $appointmentId,
            'status' => 'Pending',
        ]);
    }

    public function test_doctor_cannot_access_another_doctors_appointment(): void
    {
        [$doctor] = $this->createDoctor('Dr. Utama');
        [, $otherScheduleId] = $this->createDoctor('Dr. Lain');
        $appointmentId = $this->createAppointment($otherScheduleId, 'Checked-In', 'Pasien Dokter Lain');

        $this->actingAs($doctor->user)
            ->get(route('staff.doctor.appointments.show', $appointmentId))
            ->assertNotFound();

        $this->post(route('staff.doctor.appointments.consultation.store', $appointmentId), [
            'diagnosis' => 'Tidak boleh',
            'treatment_plan' => 'Tidak boleh',
        ])->assertNotFound();

        $this->assertDatabaseCount('doctor_consultations', 0);
    }

    public function test_non_doctor_cannot_open_doctor_workflow(): void
    {
        $receptionist = $this->createUser('Resepsionis');

        $this->actingAs($receptionist)
            ->get(route('staff.doctor.appointments.index'))
            ->assertForbidden();
    }

    /**
     * @return array{object, int}
     */
    private function createDoctor(string $name): array
    {
        $user = $this->createUser('Dokter');
        $departmentId = DB::table('departments')->insertGetId(['name' => 'Poli Umum']);
        $doctorId = DB::table('doctors')->insertGetId([
            'user_id' => $user->id,
            'department_id' => $departmentId,
            'full_name' => $name,
        ]);
        $scheduleId = DB::table('doctor_schedules')->insertGetId([
            'doctor_id' => $doctorId,
            'day_of_week' => 'Senin',
            'start_time' => '08:00:00',
            'end_time' => '12:00:00',
            'quota_limit' => 20,
        ]);

        return [(object) ['id' => $doctorId, 'user' => $user], $scheduleId];
    }

    private function createUser(string $roleName): User
    {
        $roleId = DB::table('roles')->where('role_name', $roleName)->value('id')
            ?? DB::table('roles')->insertGetId(['role_name' => $roleName]);
        $userId = DB::table('users')->insertGetId([
            'role_id' => $roleId,
            'email' => strtolower($roleName).'-'.bin2hex(random_bytes(4)).'@example.com',
            'phone_number' => '081234567890',
            'password_hash' => Hash::make('password123'),
            'is_active' => true,
        ]);

        return User::query()->findOrFail($userId);
    }

    private function createAppointment(int $scheduleId, string $status, string $patientName): int
    {
        $patientId = DB::table('patient_profiles')->insertGetId([
            'medical_record_number' => 'RM-'.strtoupper(bin2hex(random_bytes(3))),
            'full_name' => $patientName,
            'phone_number' => '081234567890',
            'gender' => 'L',
            'birth_date' => '1990-01-01',
            'address' => 'Jl. Sehat',
        ]);

        return DB::table('appointments')->insertGetId([
            'booking_code' => 'REG-'.strtoupper(bin2hex(random_bytes(4))),
            'patient_id' => $patientId,
            'doctor_schedule_id' => $scheduleId,
            'appointment_date' => Carbon::today()->toDateString(),
            'queue_number' => 1,
            'initial_complaint' => 'Demam dan batuk',
            'status' => $status,
        ]);
    }
}
