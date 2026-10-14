<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MobileApiTest extends TestCase
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
            $table->string('gender')->nullable();
            $table->date('birth_date')->nullable();
        });

        Schema::create('family_relationships', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('patient_id');
            $table->unsignedInteger('family_user_id');
            $table->string('relationship_type');
            $table->string('access_permission')->default('Read-Only');
            $table->string('status')->default('Pending');
            $table->timestamp('created_at')->nullable();
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
    }

    public function test_patient_can_log_in_with_a_token_and_revoke_it(): void
    {
        [$user, $patientId] = $this->createPatientAccount('patient@example.com', 'Maya Pasien');

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'mobile-password',
            'device_name' => 'Test phone',
        ])->assertOk()
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.user.role', 'Pasien')
            ->assertJsonPath('data.user.id', $user->id);

        $token = $response->json('data.access_token');

        $this->withToken($token)
            ->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.patient_profile.id', $patientId)
            ->assertJsonMissingPath('data.password_hash');

        $this->withToken($token)
            ->postJson('/api/v1/auth/logout')
            ->assertOk();

        $this->assertDatabaseCount('personal_access_tokens', 0);

        Auth::forgetGuards();

        $this->withToken($token)
            ->getJson('/api/v1/me')
            ->assertUnauthorized();
    }

    public function test_mobile_login_rejects_staff_accounts_and_invalid_credentials(): void
    {
        $staff = $this->createUser('Resepsionis', 'staff@example.com');
        $patient = $this->createUser('Pasien', 'patient@example.com');
        $family = $this->createUser('Keluarga', 'family@example.com');
        $this->createPatientProfile($patient->id, 'Maya Pasien');

        $this->postJson('/api/v1/auth/login', [
            'email' => $staff->email,
            'password' => 'mobile-password',
        ])->assertUnauthorized();

        $this->postJson('/api/v1/auth/login', [
            'email' => $patient->email,
            'password' => 'wrong-password',
        ])->assertUnauthorized();

        $this->postJson('/api/v1/auth/login', [
            'email' => $family->email,
            'password' => 'mobile-password',
        ])->assertOk()
            ->assertJsonPath('data.user.role', 'Keluarga');
    }

    public function test_api_rejects_missing_and_invalid_bearer_tokens(): void
    {
        $this->getJson('/api/v1/me')->assertUnauthorized();

        $this->withToken('invalid-token')
            ->getJson('/api/v1/me')
            ->assertUnauthorized();
    }

    public function test_family_can_only_read_approved_linked_patient_profiles(): void
    {
        $family = $this->createUser('Keluarga', 'family@example.com');
        $linkedPatientId = $this->createPatientProfile(null, 'Linked Patient');
        $pendingPatientId = $this->createPatientProfile(null, 'Pending Patient');
        $unrelatedPatientId = $this->createPatientProfile(null, 'Unrelated Patient');
        $this->linkFamily($family->id, $linkedPatientId, 'Approved', 'Read-Only');
        $this->linkFamily($family->id, $pendingPatientId, 'Pending', 'Full-Access');

        $this->withToken($this->issueToken($family))
            ->getJson('/api/v1/patients')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment([
                'id' => $linkedPatientId,
                'full_name' => 'Linked Patient',
                'relationship' => 'Anak',
                'access_permission' => 'Read-Only',
            ])
            ->assertJsonMissing(['id' => $pendingPatientId])
            ->assertJsonMissing(['id' => $unrelatedPatientId]);
    }

    public function test_patient_can_book_an_appointment_for_their_own_profile(): void
    {
        [$patient, $patientId] = $this->createPatientAccount('patient@example.com', 'Maya Pasien');
        $scheduleId = $this->createSchedule('Dokter Sari', 2);
        $date = Carbon::now()->next(Carbon::MONDAY)->toDateString();
        $token = $this->issueToken($patient);

        $this->withToken($token)
            ->getJson('/api/v1/schedules?date='.$date)
            ->assertOk()
            ->assertJsonPath('data.schedules.0.id', $scheduleId)
            ->assertJsonPath('data.schedules.0.remaining_slots', 2);

        Auth::forgetGuards();

        $this->withToken($token)
            ->postJson('/api/v1/appointments', [
                'patient_id' => $patientId,
                'doctor_schedule_id' => $scheduleId,
                'appointment_date' => $date,
                'initial_complaint' => 'Demam sejak kemarin',
                'consultation_type' => 'Teleconsultation',
            ])
            ->assertCreated()
            ->assertJsonPath('data.patient_id', $patientId)
            ->assertJsonPath('data.queue_number', 1)
            ->assertJsonPath('data.status', 'Pending')
            ->assertJsonPath('data.consultation_type', 'Teleconsultation');

        $this->assertDatabaseHas('appointments', [
            'patient_id' => $patientId,
            'doctor_schedule_id' => $scheduleId,
            'appointment_date' => $date,
            'queue_number' => 1,
            'initial_complaint' => 'Demam sejak kemarin',
        ]);
    }

    public function test_family_full_access_can_message_but_read_only_family_cannot(): void
    {
        $patient = $this->createUser('Pasien', 'patient@example.com');
        $patientId = $this->createPatientProfile($patient->id, 'Maya Pasien');
        $familyFullAccess = $this->createUser('Keluarga', 'family-full@example.com');
        $familyReadOnly = $this->createUser('Keluarga', 'family-read@example.com');
        $this->linkFamily($familyFullAccess->id, $patientId, 'Approved', 'Full-Access');
        $this->linkFamily($familyReadOnly->id, $patientId, 'Approved', 'Read-Only');
        $scheduleId = $this->createSchedule('Dokter Sari');
        $appointmentId = $this->createAppointment($scheduleId, $patientId, 'Teleconsultation');
        $appointmentDate = Carbon::now()->next(Carbon::MONDAY)->toDateString();

        $this->withToken($this->issueToken($familyReadOnly))
            ->getJson("/api/v1/teleconsultations/{$appointmentId}/messages")
            ->assertOk()
            ->assertJsonPath('data.can_send_messages', false);

        Auth::forgetGuards();

        $this->withToken($this->issueToken($familyReadOnly))
            ->postJson('/api/v1/appointments', [
                'patient_id' => $patientId,
                'doctor_schedule_id' => $scheduleId,
                'appointment_date' => $appointmentDate,
            ])
            ->assertNotFound();

        Auth::forgetGuards();

        $fullAccessToken = $this->issueToken($familyFullAccess);

        $this->withToken($fullAccessToken)
            ->postJson('/api/v1/appointments', [
                'patient_id' => $patientId,
                'doctor_schedule_id' => $scheduleId,
                'appointment_date' => $appointmentDate,
                'consultation_type' => 'Teleconsultation',
            ])
            ->assertCreated();

        Auth::forgetGuards();

        $this->withToken($this->issueToken($familyReadOnly))
            ->postJson("/api/v1/teleconsultations/{$appointmentId}/messages", [
                'message' => 'Pesan yang tidak boleh terkirim',
            ])
            ->assertForbidden();

        Auth::forgetGuards();

        $response = $this->withToken($fullAccessToken)
            ->postJson("/api/v1/teleconsultations/{$appointmentId}/messages", [
                'message' => 'Mohon informasi kondisi pasien.',
            ]);

        $this->assertSame(201, $response->status(), $response->content());

        $this->assertDatabaseHas('teleconsultation_messages', [
            'appointment_id' => $appointmentId,
            'sender_user_id' => $familyFullAccess->id,
            'message' => 'Mohon informasi kondisi pasien.',
        ]);

        Auth::forgetGuards();

        $this->withToken($fullAccessToken)
            ->getJson("/api/v1/teleconsultations/{$appointmentId}/messages")
            ->assertOk()
            ->assertJsonPath('data.messages.data.0.message', 'Mohon informasi kondisi pasien.');
    }

    public function test_family_cannot_access_an_unlinked_patient_teleconsultation(): void
    {
        $family = $this->createUser('Keluarga', 'family@example.com');
        $patientId = $this->createPatientProfile(null, 'Unrelated Patient');
        $scheduleId = $this->createSchedule('Dokter Sari');
        $appointmentId = $this->createAppointment($scheduleId, $patientId, 'Teleconsultation');

        $this->withToken($this->issueToken($family))
            ->getJson("/api/v1/teleconsultations/{$appointmentId}/messages")
            ->assertNotFound();
    }

    private function createUser(string $role, string $email): User
    {
        $roleId = DB::table('roles')->where('role_name', $role)->value('id');

        if ($roleId === null) {
            $roleId = DB::table('roles')->insertGetId(['role_name' => $role]);
        }

        $userId = DB::table('users')->insertGetId([
            'role_id' => $roleId,
            'email' => $email,
            'phone_number' => '081234567890',
            'password_hash' => Hash::make('mobile-password'),
            'is_active' => true,
            'created_at' => now(),
        ]);

        return User::query()->findOrFail($userId);
    }

    /**
     * @return array{User, int}
     */
    private function createPatientAccount(string $email, string $fullName): array
    {
        $user = $this->createUser('Pasien', $email);

        return [$user, $this->createPatientProfile($user->id, $fullName)];
    }

    private function createPatientProfile(?int $userId, string $fullName): int
    {
        $patientId = DB::table('patient_profiles')->insertGetId([
            'user_id' => $userId,
            'medical_record_number' => 'RM-'.str_pad((string) (DB::table('patient_profiles')->count() + 1), 8, '0', STR_PAD_LEFT),
            'full_name' => $fullName,
            'phone_number' => '081234567890',
            'gender' => 'P',
            'birth_date' => '1990-01-01',
        ]);

        return (int) $patientId;
    }

    private function linkFamily(int $familyUserId, int $patientId, string $status, string $permission): void
    {
        DB::table('family_relationships')->insert([
            'patient_id' => $patientId,
            'family_user_id' => $familyUserId,
            'relationship_type' => 'Anak',
            'access_permission' => $permission,
            'status' => $status,
            'created_at' => now(),
        ]);
    }

    private function createSchedule(string $doctorName, int $quota = 5): int
    {
        $doctorUser = $this->createUser('Dokter', strtolower(str_replace(' ', '.', $doctorName)).'@example.com');
        $departmentId = DB::table('departments')->insertGetId(['name' => 'Poli Umum']);
        $doctorId = DB::table('doctors')->insertGetId([
            'user_id' => $doctorUser->id,
            'department_id' => $departmentId,
            'full_name' => $doctorName,
        ]);

        return (int) DB::table('doctor_schedules')->insertGetId([
            'doctor_id' => $doctorId,
            'day_of_week' => 'Senin',
            'start_time' => '08:00:00',
            'end_time' => '12:00:00',
            'quota_limit' => $quota,
        ]);
    }

    private function createAppointment(int $scheduleId, int $patientId, string $consultationType): int
    {
        return (int) DB::table('appointments')->insertGetId([
            'booking_code' => 'REG-'.str_pad((string) (DB::table('appointments')->count() + 1), 6, '0', STR_PAD_LEFT),
            'patient_id' => $patientId,
            'doctor_schedule_id' => $scheduleId,
            'appointment_date' => Carbon::now()->next(Carbon::MONDAY)->toDateString(),
            'queue_number' => DB::table('appointments')->count() + 1,
            'consultation_type' => $consultationType,
            'status' => 'Pending',
            'created_at' => now(),
        ]);
    }

    private function issueToken(User $user): string
    {
        return $user->createToken('test-mobile', ['mobile:read', 'mobile:write'])->plainTextToken;
    }
}
