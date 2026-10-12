<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TeleconsultationTest extends TestCase
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
            $table->unsignedInteger('user_id')->nullable();
            $table->string('medical_record_number')->nullable();
            $table->string('full_name');
            $table->string('phone_number')->nullable();
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

        Schema::create('teleconsultation_messages', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('appointment_id');
            $table->unsignedInteger('sender_user_id');
            $table->text('message');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function test_patient_can_sign_in_and_see_only_their_teleconsultations(): void
    {
        [$doctorUser, $scheduleId] = $this->createDoctor('Dr. Sari');
        [$patientUser, $patientId] = $this->createPatient('Maya Pasien');
        $appointmentId = $this->createAppointment($scheduleId, $patientId);
        [, $otherPatientId] = $this->createPatient('Pasien Lain');
        $otherAppointmentId = $this->createAppointment($scheduleId, $otherPatientId);

        $this->post(route('patient.login.attempt'), [
            'email' => $patientUser->email,
            'password' => 'patient-password',
        ])
            ->assertRedirect(route('patient.teleconsultations.index'));

        $this        ->get(route('patient.teleconsultations.index'))
        ->assertOk()
        ->assertSeeText('Dr. Sari')
            ->assertDontSeeText('Pasien Lain')
            ->assertSee(route('patient.teleconsultations.show', $appointmentId), false)
            ->assertDontSee(route('patient.teleconsultations.show', $otherAppointmentId), false);

        $this->assertSame('Dokter', DB::table('roles')->where('id', $doctorUser->role_id)->value('role_name'));
    }

    public function test_patient_account_without_a_linked_profile_cannot_enter_the_teleconsultation_portal(): void
    {
        $patientUser = $this->createUser('Pasien', 'unlinked-patient@example.com');

        $this->from(route('patient.login'))
            ->post(route('patient.login.attempt'), [
                'email' => $patientUser->email,
                'password' => 'patient-password',
            ])
            ->assertRedirect(route('patient.login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_patient_and_assigned_doctor_can_exchange_messages(): void
    {
        [$doctorUser, $scheduleId] = $this->createDoctor('Dr. Sari');
        [$patientUser, $patientId] = $this->createPatient('Maya Pasien');
        $appointmentId = $this->createAppointment($scheduleId, $patientId);

        $this->actingAs($patientUser)
            ->post(route('patient.teleconsultations.messages.store', $appointmentId), [
                'message' => 'Saya demam sejak kemarin.',
            ])
            ->assertRedirect(route('patient.teleconsultations.show', $appointmentId));

        $this->actingAs($doctorUser)
            ->post(route('staff.doctor.teleconsultations.messages.store', $appointmentId), [
                'message' => 'Apakah disertai batuk atau sesak?',
            ])
            ->assertRedirect(route('staff.doctor.teleconsultations.show', $appointmentId));

        $this->actingAs($patientUser)
            ->get(route('patient.teleconsultations.show', $appointmentId))
            ->assertOk()
            ->assertSeeText('Saya demam sejak kemarin.')
            ->assertSeeText('Apakah disertai batuk atau sesak?')
            ->assertSeeText('Dr. Sari');

        $this->assertDatabaseCount('teleconsultation_messages', 2);
    }

    public function test_only_teleconsultation_appointments_can_be_chatted(): void
    {
        [, $scheduleId] = $this->createDoctor('Dr. Sari');
        [$patientUser, $patientId] = $this->createPatient('Maya Pasien');
        $appointmentId = $this->createAppointment($scheduleId, $patientId, 'In-Person');

        $this->actingAs($patientUser)
            ->get(route('patient.teleconsultations.show', $appointmentId))
            ->assertNotFound();
    }

    public function test_completed_teleconsultation_is_read_only_and_doctor_can_complete_it(): void
    {
        [$doctorUser, $scheduleId] = $this->createDoctor('Dr. Sari');
        [$patientUser, $patientId] = $this->createPatient('Maya Pasien');
        $appointmentId = $this->createAppointment($scheduleId, $patientId);

        $this->actingAs($doctorUser)
            ->post(route('staff.doctor.teleconsultations.complete', $appointmentId))
            ->assertRedirect(route('staff.doctor.teleconsultations.show', $appointmentId))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('appointments', [
            'id' => $appointmentId,
            'status' => 'Completed',
        ]);

        $this->actingAs($patientUser)
            ->get(route('patient.teleconsultations.show', $appointmentId))
            ->assertOk()
            ->assertSeeText('riwayat hanya-baca')
            ->assertDontSee('name="message"', false);

        $this->post(route('patient.teleconsultations.messages.store', $appointmentId), [
            'message' => 'Pesan setelah selesai',
        ])->assertSessionHasErrors('message');
    }

    public function test_other_users_and_staff_cannot_access_patient_chat(): void
    {
        [, $scheduleId] = $this->createDoctor('Dr. Sari');
        [$patientUser, $patientId] = $this->createPatient('Maya Pasien');
        $appointmentId = $this->createAppointment($scheduleId, $patientId);
        $otherPatient = $this->createUser('Pasien', 'other-patient@example.com');
        $staff = $this->createUser('Resepsionis', 'reception@example.com');

        $this->actingAs($otherPatient)
            ->get(route('patient.teleconsultations.show', $appointmentId))
            ->assertNotFound();

        $this->actingAs($staff)
            ->get(route('patient.teleconsultations.index'))
            ->assertForbidden();

        $this->assertDatabaseHas('patient_profiles', ['id' => $patientId, 'user_id' => $patientUser->id]);
    }

    private function createPatient(string $name): array
    {
        $user = $this->createUser('Pasien', strtolower(str_replace(' ', '.', $name)).'@example.com');
        $patientId = DB::table('patient_profiles')->insertGetId([
            'user_id' => $user->id,
            'medical_record_number' => 'RM-'.strtoupper(bin2hex(random_bytes(3))),
            'full_name' => $name,
            'phone_number' => '081234567890',
        ]);

        return [$user, $patientId];
    }

    private function createDoctor(string $name): array
    {
        $user = $this->createUser('Dokter', strtolower(str_replace(' ', '.', $name)).'@example.com');
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
            'quota_limit' => 10,
        ]);

        return [$user, $scheduleId];
    }

    private function createUser(string $roleName, string $email): User
    {
        $roleId = DB::table('roles')->where('role_name', $roleName)->value('id')
            ?? DB::table('roles')->insertGetId(['role_name' => $roleName]);
        $userId = DB::table('users')->insertGetId([
            'role_id' => $roleId,
            'email' => $email,
            'phone_number' => '081234567890',
            'password_hash' => Hash::make('patient-password'),
            'is_active' => true,
        ]);

        return User::query()->findOrFail($userId);
    }

    private function createAppointment(int $scheduleId, int $patientId, string $type = 'Teleconsultation'): int
    {
        return DB::table('appointments')->insertGetId([
            'booking_code' => 'REG-'.strtoupper(bin2hex(random_bytes(4))),
            'patient_id' => $patientId,
            'doctor_schedule_id' => $scheduleId,
            'appointment_date' => Carbon::today()->toDateString(),
            'queue_number' => 1,
            'initial_complaint' => 'Demam sejak kemarin',
            'consultation_type' => $type,
            'status' => 'Confirmed',
        ]);
    }
}
