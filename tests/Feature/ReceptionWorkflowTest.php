<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ReceptionWorkflowTest extends TestCase
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
            $table->string('national_id', 16)->nullable()->unique();
            $table->string('full_name');
            $table->string('phone_number', 20)->nullable();
            $table->string('emergency_contact', 20)->nullable();
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

        Schema::create('check_in_logs', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('appointment_id');
            $table->unsignedInteger('verified_by_user_id');
        });
    }

    public function test_receptionist_can_register_a_walk_in_patient_without_an_app_account(): void
    {
        $this->actingAs($this->createUser('Resepsionis'))
            ->post(route('staff.reception.patients.store'), [
                'full_name' => 'Maya Pasien',
                'national_id' => '3201010101010001',
                'phone_number' => '081234567890',
                'emergency_contact' => '081234567891',
                'gender' => 'P',
                'birth_date' => '1992-04-12',
                'address' => 'Jl. Melati No. 2',
                'for_registration' => '1',
            ])
            ->assertRedirect(route('staff.reception.registrations.create', ['patient_id' => 1]));

        $this->assertDatabaseHas('patient_profiles', [
            'user_id' => null,
            'medical_record_number' => 'RM-00000001',
            'national_id' => '3201010101010001',
            'full_name' => 'Maya Pasien',
        ]);
    }

    public function test_receptionist_can_search_patients_by_medical_record_number(): void
    {
        $patientId = $this->createPatient('Maya Pasien', 'RM-00000042');

        $this->actingAs($this->createUser('Resepsionis'))
            ->get(route('staff.reception.patients.index', ['search' => 'RM-00000042']))
            ->assertOk()
            ->assertSeeText('Maya Pasien')
            ->assertSeeText('RM-00000042');

        $this->assertDatabaseHas('patient_profiles', ['id' => $patientId]);
    }

    public function test_receptionist_can_create_an_appointment_and_receive_the_next_queue_number(): void
    {
        $user = $this->createUser('Resepsionis');
        $patientId = $this->createPatient('Maya Pasien');
        [$scheduleId, $date] = $this->createSchedule(quota: 3);

        $this->actingAs($user)
            ->post(route('staff.reception.registrations.store'), [
                'patient_id' => $patientId,
                'doctor_schedule_id' => $scheduleId,
                'appointment_date' => $date,
                'initial_complaint' => 'Demam sejak kemarin',
            ])
            ->assertRedirect(route('staff.reception.registrations.index', ['date' => $date]))
            ->assertSessionHas('success', 'Pendaftaran berhasil. Nomor antrean 001.');

        $this->assertDatabaseHas('appointments', [
            'patient_id' => $patientId,
            'doctor_schedule_id' => $scheduleId,
            'appointment_date' => $date,
            'queue_number' => 1,
            'initial_complaint' => 'Demam sejak kemarin',
            'status' => 'Pending',
        ]);
    }

    public function test_receptionist_can_register_a_teleconsultation(): void
    {
        $user = $this->createUser('Resepsionis');
        $patientId = $this->createPatient('Maya Pasien');
        [$scheduleId, $date] = $this->createSchedule();

        $this->actingAs($user)
            ->post(route('staff.reception.registrations.store'), [
                'patient_id' => $patientId,
                'doctor_schedule_id' => $scheduleId,
                'appointment_date' => $date,
                'consultation_type' => 'Teleconsultation',
            ])
            ->assertRedirect(route('staff.reception.registrations.index', ['date' => $date]));

        $this->assertDatabaseHas('appointments', [
            'patient_id' => $patientId,
            'consultation_type' => 'Teleconsultation',
            'status' => 'Pending',
        ]);
    }

    public function test_registration_rejects_the_wrong_weekday_and_a_full_or_zero_quota(): void
    {
        $user = $this->createUser('Resepsionis');
        $patientId = $this->createPatient('Maya Pasien');
        [$scheduleId, $date] = $this->createSchedule(quota: 1);

        $this->actingAs($user)
            ->post(route('staff.reception.registrations.store'), [
                'patient_id' => $patientId,
                'doctor_schedule_id' => $scheduleId,
                'appointment_date' => Carbon::parse($date)->addDay()->toDateString(),
            ])
            ->assertSessionHasErrors('doctor_schedule_id');

        $this->createAppointment($patientId, $scheduleId, $date, 'Pending', 1);

        $this->actingAs($user)
            ->post(route('staff.reception.registrations.store'), [
                'patient_id' => $patientId,
                'doctor_schedule_id' => $scheduleId,
                'appointment_date' => $date,
            ])
            ->assertSessionHasErrors('doctor_schedule_id');

        DB::table('doctor_schedules')->where('id', $scheduleId)->update(['quota_limit' => 0]);
        DB::table('appointments')->where('doctor_schedule_id', $scheduleId)->delete();

        $this->actingAs($user)
            ->post(route('staff.reception.registrations.store'), [
                'patient_id' => $patientId,
                'doctor_schedule_id' => $scheduleId,
                'appointment_date' => $date,
            ])
            ->assertSessionHasErrors('doctor_schedule_id');

        $this->assertDatabaseCount('appointments', 0);
    }

    public function test_receptionist_can_check_in_a_patient_and_the_action_is_logged(): void
    {
        $user = $this->createUser('Resepsionis');
        $patientId = $this->createPatient('Maya Pasien');
        [$scheduleId, $date] = $this->createSchedule();
        $appointmentId = $this->createAppointment($patientId, $scheduleId, $date);

        $this->actingAs($user)
            ->post(route('staff.reception.registrations.check-in', $appointmentId))
            ->assertRedirect();

        $this->assertDatabaseHas('appointments', [
            'id' => $appointmentId,
            'status' => 'Checked-In',
        ]);
        $this->assertDatabaseHas('check_in_logs', [
            'appointment_id' => $appointmentId,
            'verified_by_user_id' => $user->id,
        ]);
    }

    public function test_receptionist_can_cancel_an_unchecked_in_registration(): void
    {
        $this->actingAs($this->createUser('Resepsionis'));
        $patientId = $this->createPatient('Maya Pasien');
        [$scheduleId, $date] = $this->createSchedule();
        $appointmentId = $this->createAppointment($patientId, $scheduleId, $date);

        $this->post(route('staff.reception.registrations.cancel', $appointmentId))
            ->assertRedirect();

        $this->assertDatabaseHas('appointments', [
            'id' => $appointmentId,
            'status' => 'Cancelled',
        ]);
    }

    public function test_only_receptionists_and_admins_can_open_reception_screens(): void
    {
        $this->actingAs($this->createUser('Dokter'))
            ->get(route('staff.reception.patients.index'))
            ->assertForbidden();

        $this->actingAs($this->createUser('Admin'))
            ->get(route('staff.reception.patients.index'))
            ->assertOk();
    }

    private function createUser(string $roleName): User
    {
        $roleId = DB::table('roles')->insertGetId(['role_name' => $roleName]);
        $userId = DB::table('users')->insertGetId([
            'role_id' => $roleId,
            'email' => strtolower($roleName).'-'.$roleId.'@example.com',
            'phone_number' => '081234567890',
            'password_hash' => Hash::make('password123'),
            'is_active' => true,
        ]);

        return User::query()->findOrFail($userId);
    }

    private function createPatient(string $name, ?string $medicalRecordNumber = null): int
    {
        return DB::table('patient_profiles')->insertGetId([
            'full_name' => $name,
            'medical_record_number' => $medicalRecordNumber,
        ]);
    }

    /**
     * @return array{int, string}
     */
    private function createSchedule(int $quota = 10): array
    {
        $dayNames = [
            1 => 'Senin',
            2 => 'Selasa',
            3 => 'Rabu',
            4 => 'Kamis',
            5 => 'Jumat',
            6 => 'Sabtu',
            7 => 'Minggu',
        ];
        $date = Carbon::today();
        $dayOfWeek = $dayNames[$date->dayOfWeekIso];

        $departmentId = DB::table('departments')->insertGetId(['name' => 'Poli Umum']);
        $doctorId = DB::table('doctors')->insertGetId([
            'user_id' => $this->createUser('Dokter')->id,
            'department_id' => $departmentId,
            'full_name' => 'Dr. Sari',
        ]);
        $scheduleId = DB::table('doctor_schedules')->insertGetId([
            'doctor_id' => $doctorId,
            'day_of_week' => $dayOfWeek,
            'start_time' => '08:00:00',
            'end_time' => '12:00:00',
            'quota_limit' => $quota,
        ]);

        return [$scheduleId, $date->toDateString()];
    }

    private function createAppointment(
        int $patientId,
        int $scheduleId,
        string $date,
        string $status = 'Pending',
        int $queueNumber = 1,
    ): int {
        return DB::table('appointments')->insertGetId([
            'booking_code' => 'REG-'.str_replace('-', '', $date).'-'.strtoupper(bin2hex(random_bytes(3))),
            'patient_id' => $patientId,
            'doctor_schedule_id' => $scheduleId,
            'appointment_date' => $date,
            'queue_number' => $queueNumber,
            'status' => $status,
        ]);
    }
}
