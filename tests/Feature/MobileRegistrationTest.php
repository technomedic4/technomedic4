<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MobileRegistrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('personal_access_tokens');
        Schema::dropIfExists('patient_profiles');
        Schema::dropIfExists('users');
        Schema::dropIfExists('roles');

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
            $table->boolean('is_active')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('patient_profiles', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('user_id')->nullable();
            $table->string('medical_record_number')->nullable();
            $table->string('national_id', 16)->nullable()->unique();
            $table->string('full_name');
            $table->string('phone_number', 20)->nullable();
            $table->date('birth_date')->nullable();
            $table->string('gender', 1)->nullable();
            $table->text('address')->nullable();
        });

        Schema::create('personal_access_tokens', function (Blueprint $table): void {
            $table->id();
            $table->morphs('tokenable');
            $table->text('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('departments', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('name');
        });

        Schema::create('doctors', function (Blueprint $table): void {
            $table->increments('id');
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
            $table->string('consultation_type');
            $table->string('status');
        });

        DB::table('roles')->insert(['role_name' => 'Pasien']);
    }

    public function test_patient_can_register_and_use_the_created_account(): void
    {
        $response = $this->postJson('/api/v1/auth/register', $this->registrationData());

        $response
            ->assertCreated()
            ->assertJsonPath('data.token_type', 'Bearer');

        $this->assertDatabaseHas('users', [
            'email' => 'rina@example.test',
            'phone_number' => '081234567890',
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('patient_profiles', [
            'national_id' => '3174050505950005',
            'full_name' => 'Rina Pratama',
            'medical_record_number' => 'RM-00000001',
        ]);
        $this->assertTrue(Hash::check(
            'aman1234',
            (string) DB::table('users')->where('email', 'rina@example.test')->value('password_hash'),
        ));
        $this->assertDatabaseCount('personal_access_tokens', 1);

        $token = $response->json('data.access_token');
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.email', 'rina@example.test')
            ->assertJsonPath('data.patient_profile.full_name', 'Rina Pratama');
    }

    public function test_registration_rejects_a_nik_already_in_the_patient_database(): void
    {
        DB::table('patient_profiles')->insert([
            'user_id' => null,
            'national_id' => '3174050505950005',
            'full_name' => 'Existing Patient',
        ]);

        $this->postJson('/api/v1/auth/register', $this->registrationData())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('nik');

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('patient_profiles', 1);
    }

    public function test_mobile_registered_patient_can_be_found_and_registered_by_reception(): void
    {
        $this->postJson('/api/v1/auth/register', $this->registrationData())
            ->assertCreated();

        $patientId = (int) DB::table('patient_profiles')
            ->where('national_id', '3174050505950005')
            ->value('id');

        $receptionistRoleId = DB::table('roles')->insertGetId(['role_name' => 'Resepsionis']);
        $receptionistId = DB::table('users')->insertGetId([
            'role_id' => $receptionistRoleId,
            'email' => 'reception@example.test',
            'password_hash' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $departmentId = DB::table('departments')->insertGetId(['name' => 'Poli Umum']);
        $doctorId = DB::table('doctors')->insertGetId([
            'department_id' => $departmentId,
            'full_name' => 'Dr. Sari',
        ]);
        $scheduleId = DB::table('doctor_schedules')->insertGetId([
            'doctor_id' => $doctorId,
            'day_of_week' => 'Senin',
            'start_time' => '08:00:00',
            'end_time' => '12:00:00',
            'quota_limit' => 10,
        ]);

        $this->actingAs(User::query()->findOrFail($receptionistId))
            ->get(route('staff.reception.patients.index', ['search' => 'Rina Pratama']))
            ->assertOk()
            ->assertSeeText('Rina Pratama')
            ->assertSeeText('Terhubung')
            ->assertSee(route('staff.reception.registrations.create', ['patient_id' => $patientId]), false);

        $date = Carbon::now()->next(Carbon::MONDAY)->toDateString();

        $this->get(route('staff.reception.registrations.create', ['patient_id' => $patientId, 'date' => $date]))
            ->assertOk()
            ->assertSeeText('Rina Pratama');

        $this->post(route('staff.reception.registrations.store'), [
            'patient_id' => $patientId,
            'doctor_schedule_id' => $scheduleId,
            'appointment_date' => $date,
            'initial_complaint' => 'Pemeriksaan awal',
        ])->assertRedirect(route('staff.reception.registrations.index', ['date' => $date]));

        $this->assertDatabaseHas('appointments', [
            'patient_id' => $patientId,
            'doctor_schedule_id' => $scheduleId,
            'appointment_date' => $date,
            'status' => 'Pending',
        ]);
    }

    private function registrationData(): array
    {
        return [
            'name' => 'Rina Pratama',
            'nik' => '3174050505950005',
            'phone' => '081234567890',
            'birth_date' => '1995-05-05',
            'gender' => 'P',
            'address' => 'Jakarta',
            'email' => 'RINA@example.test',
            'password' => 'aman1234',
            'password_confirmation' => 'aman1234',
            'device_name' => 'SIMRS Sehat',
        ];
    }
}
