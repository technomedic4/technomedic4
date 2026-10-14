<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MedicalRecordWorkflowTest extends TestCase
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
            $table->string('national_id', 16)->nullable();
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

    public function test_receptionist_can_search_for_a_patient_and_open_their_medical_record(): void
    {
        $user = $this->createUser('Resepsionis');
        $patientId = $this->createPatient('Maya Pasien', 'RM-00000042');
        [$doctorId, $scheduleId] = $this->createDoctorSchedule('Dr. Farid');
        $appointmentId = $this->createAppointment($patientId, $scheduleId, 'Completed', 'Demam 3 hari');

        DB::table('doctor_consultations')->insert([
            'appointment_id' => $appointmentId,
            'patient_id' => $patientId,
            'doctor_id' => $doctorId,
            'diagnosis' => 'Infeksi saluran pernapasan atas',
            'treatment_plan' => 'Istirahat dan obat sesuai kebutuhan.',
            'notes' => 'Kontrol kembali jika tidak membaik.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('staff.medical-records.index', ['search' => 'RM-00000042']))
            ->assertOk()
            ->assertSeeText('Maya Pasien')
            ->assertSeeText('RM-00000042');

        $this->actingAs($user)
            ->get(route('staff.medical-records.show', $patientId))
            ->assertOk()
            ->assertSeeText('Maya Pasien')
            ->assertSeeText('Infeksi saluran pernapasan atas');
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
            'created_at' => now(),
        ]);

        return User::query()->findOrFail($userId);
    }

    private function createPatient(string $name, string $mrn = 'RM-00000001'): int
    {
        return DB::table('patient_profiles')->insertGetId([
            'user_id' => null,
            'medical_record_number' => $mrn,
            'national_id' => '3201010101010001',
            'full_name' => $name,
            'phone_number' => '081200011122',
            'emergency_contact' => '081200011123',
            'gender' => 'P',
            'birth_date' => '1995-01-15',
            'address' => 'Jl. Melati No. 5',
        ]);
    }

    private function createDoctorSchedule(string $doctorName): array
    {
        $departmentId = DB::table('departments')->insertGetId(['name' => 'Umum']);
        $doctorId = DB::table('doctors')->insertGetId([
            'user_id' => 1,
            'department_id' => $departmentId,
            'full_name' => $doctorName,
        ]);
        $scheduleId = DB::table('doctor_schedules')->insertGetId([
            'doctor_id' => $doctorId,
            'day_of_week' => 'Senin',
            'start_time' => '09:00:00',
            'end_time' => '10:00:00',
            'quota_limit' => 10,
        ]);

        return [$doctorId, $scheduleId];
    }

    private function createAppointment(int $patientId, int $scheduleId, string $status, string $complaint): int
    {
        return DB::table('appointments')->insertGetId([
            'booking_code' => 'BK-'.uniqid(),
            'patient_id' => $patientId,
            'doctor_schedule_id' => $scheduleId,
            'appointment_date' => now()->toDateString(),
            'queue_number' => 1,
            'initial_complaint' => $complaint,
            'consultation_type' => 'In-Person',
            'status' => $status,
            'created_at' => now(),
        ]);
    }
}
