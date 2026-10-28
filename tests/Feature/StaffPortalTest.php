<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class StaffPortalTest extends TestCase
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
            $table->boolean('is_active')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('patient_profiles', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('full_name');
        });

        Schema::create('bills', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('bill_number');
            $table->unsignedInteger('patient_id');
            $table->unsignedBigInteger('total_amount');
            $table->unsignedBigInteger('amount_paid')->default(0);
            $table->string('status');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('bill_payments', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('bill_id');
            $table->unsignedBigInteger('amount');
            $table->timestamp('paid_at');
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
            $table->time('start_time');
        });

        Schema::create('appointments', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('booking_code');
            $table->unsignedInteger('patient_id');
            $table->unsignedInteger('doctor_schedule_id');
            $table->date('appointment_date');
            $table->unsignedInteger('queue_number');
            $table->string('status');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('patient_care_updates', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('patient_id');
            $table->unsignedInteger('appointment_id')->nullable();
            $table->unsignedInteger('inpatient_admission_id')->nullable();
            $table->string('update_status');
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function test_guest_is_redirected_to_the_staff_login(): void
    {
        $this->get('/staff')
            ->assertRedirect(route('login'));

        $this->get(route('login'))
            ->assertOk()
            ->assertSeeText('Masuk ke portal staf')
            ->assertDontSeeText('Daftar Disini')
            ->assertDontSeeText('Masuk sebagai pasien');
    }

    public function test_patient_account_cannot_sign_in_to_the_staff_portal(): void
    {
        $this->createUser('Pasien', 'patient@example.com');

        $this->post(route('login.attempt'), [
            'email' => 'patient@example.com',
            'password' => 'password123',
        ])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_patient_account_cannot_access_the_staff_dashboard_directly(): void
    {
        $userId = $this->createUser('Pasien', 'patient@example.com');

        $this->actingAs(User::query()->findOrFail($userId))
            ->get(route('staff.dashboard'))
            ->assertForbidden();
    }

    public function test_inactive_staff_account_cannot_sign_in(): void
    {
        $this->createUser('Resepsionis', 'inactive@example.com', false);

        $this->post(route('login.attempt'), [
            'email' => 'inactive@example.com',
            'password' => 'password123',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_receptionist_sees_real_appointments_on_the_dashboard(): void
    {
        $userId = $this->createUser('Resepsionis', 'reception@example.com');
        $departmentId = DB::table('departments')->insertGetId(['name' => 'Poli Umum']);
        $doctorId = DB::table('doctors')->insertGetId([
            'user_id' => $userId,
            'department_id' => $departmentId,
            'full_name' => 'Dr. Sari',
        ]);
        $patientId = DB::table('patient_profiles')->insertGetId(['full_name' => 'Maya Pasien']);
        $this->createAppointment($doctorId, $patientId, 'A-001');

        $this->post(route('login.attempt'), [
            'email' => 'reception@example.com',
            'password' => 'password123',
        ])->assertRedirect(route('staff.dashboard'));

        $this->get(route('staff.dashboard'))
            ->assertOk()
            ->assertSeeText('Maya Pasien')
            ->assertSeeText('A-001')
            ->assertSeeText('Poli Umum');
    }

    public function test_doctor_only_sees_appointments_from_their_own_schedule(): void
    {
        $doctorUserId = $this->createUser('Dokter', 'doctor@example.com');
        $otherDoctorUserId = $this->createUser('Dokter', 'other-doctor@example.com');
        $departmentId = DB::table('departments')->insertGetId(['name' => 'Poli Anak']);
        $doctorId = DB::table('doctors')->insertGetId([
            'user_id' => $doctorUserId,
            'department_id' => $departmentId,
            'full_name' => 'Dr. Utama',
        ]);
        $otherDoctorId = DB::table('doctors')->insertGetId([
            'user_id' => $otherDoctorUserId,
            'department_id' => $departmentId,
            'full_name' => 'Dr. Lain',
        ]);
        $firstPatientId = DB::table('patient_profiles')->insertGetId(['full_name' => 'Pasien Dokter Utama']);
        $secondPatientId = DB::table('patient_profiles')->insertGetId(['full_name' => 'Pasien Dokter Lain']);
        $this->createAppointment($doctorId, $firstPatientId, 'D-001');
        $this->createAppointment($otherDoctorId, $secondPatientId, 'D-002');

        $this->post(route('login.attempt'), [
            'email' => 'doctor@example.com',
            'password' => 'password123',
        ])->assertRedirect(route('staff.dashboard'));

        $this->get(route('staff.dashboard'))
            ->assertOk()
            ->assertSeeText('Pasien Dokter Utama')
            ->assertDontSeeText('Pasien Dokter Lain')
            ->assertDontSeeText('Tagihan');
    }

    public function test_finance_staff_sees_billing_summary_on_the_dashboard(): void
    {
        $this->createUser('Keuangan', 'finance@example.com');
        $patientId = DB::table('patient_profiles')->insertGetId(['full_name' => 'Pasien Keuangan']);
        DB::table('bills')->insert([
            'bill_number' => 'INV-TEST-001',
            'patient_id' => $patientId,
            'total_amount' => 50000,
            'amount_paid' => 0,
            'status' => 'Unpaid',
            'created_at' => now(),
        ]);

        $this->post(route('login.attempt'), [
            'email' => 'finance@example.com',
            'password' => 'password123',
        ])->assertRedirect(route('staff.dashboard'));

        $this->get(route('staff.dashboard'))
            ->assertOk()
            ->assertSeeText('Tagihan tercatat')
            ->assertSeeText('Tagihan terbaru')
            ->assertSeeText('INV-TEST-001')
            ->assertDontSeeText('Pendaftaran hari ini');
    }

    private function createUser(string $roleName, string $email, bool $isActive = true): int
    {
        $roleId = DB::table('roles')->where('role_name', $roleName)->value('id')
            ?? DB::table('roles')->insertGetId(['role_name' => $roleName]);

        return DB::table('users')->insertGetId([
            'role_id' => $roleId,
            'email' => $email,
            'phone_number' => '081234567890',
            'password_hash' => Hash::make('password123'),
            'is_active' => $isActive,
        ]);
    }

    private function createAppointment(int $doctorId, int $patientId, string $bookingCode): void
    {
        $scheduleId = DB::table('doctor_schedules')->insertGetId([
            'doctor_id' => $doctorId,
            'start_time' => '08:00:00',
        ]);

        DB::table('appointments')->insert([
            'booking_code' => $bookingCode,
            'patient_id' => $patientId,
            'doctor_schedule_id' => $scheduleId,
            'appointment_date' => now()->toDateString(),
            'queue_number' => 1,
            'status' => 'Pending',
        ]);
    }
}
