<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class InpatientNursingWorkflowTest extends TestCase
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
            $table->string('full_name');
            $table->string('gender', 1)->nullable();
            $table->date('birth_date')->nullable();
        });

        Schema::create('inpatient_admissions', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('patient_id');
            $table->string('room_number', 30)->nullable();
            $table->string('bed_number', 30)->nullable();
            $table->text('admission_reason');
            $table->unsignedInteger('admitted_by_user_id');
            $table->timestamp('admitted_at');
            $table->unsignedInteger('discharged_by_user_id')->nullable();
            $table->timestamp('discharged_at')->nullable();
            $table->timestamps();
        });

        Schema::create('patient_care_updates', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('appointment_id')->nullable();
            $table->unsignedInteger('patient_id');
            $table->unsignedInteger('inpatient_admission_id')->nullable();
            $table->unsignedInteger('recorded_by_user_id')->nullable();
            $table->string('update_status', 100);
            $table->decimal('temperature_celsius', 4, 1)->nullable();
            $table->string('blood_pressure', 15)->nullable();
            $table->unsignedSmallInteger('pulse_rate')->nullable();
            $table->unsignedSmallInteger('respiratory_rate')->nullable();
            $table->unsignedTinyInteger('oxygen_saturation')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function test_receptionist_can_admit_a_patient_and_prevent_duplicate_active_admissions(): void
    {
        $receptionist = $this->createUser('Resepsionis');
        $patientId = $this->createPatient();

        $this->actingAs($receptionist)
            ->get(route('staff.inpatient-admissions.index'))
            ->assertOk()
            ->assertSeeText('Pasien rawat inap');

        $this->get(route('staff.inpatient-admissions.create'))
            ->assertOk()
            ->assertSeeText('Catat pasien masuk')
            ->assertSeeText('Rina Pasien');

        $this->post(route('staff.inpatient-admissions.store'), [
            'patient_id' => $patientId,
            'room_number' => '201',
            'bed_number' => 'B',
            'admission_reason' => 'Observasi pasca tindakan.',
        ])
            ->assertRedirect(route('staff.inpatient-admissions.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('inpatient_admissions', [
            'patient_id' => $patientId,
            'room_number' => '201',
            'bed_number' => 'B',
            'admission_reason' => 'Observasi pasca tindakan.',
            'admitted_by_user_id' => $receptionist->id,
            'discharged_at' => null,
        ]);

        $this->post(route('staff.inpatient-admissions.store'), [
            'patient_id' => $patientId,
            'admission_reason' => 'Duplikasi.',
        ])->assertSessionHasErrors('patient_id');

        $this->assertDatabaseCount('inpatient_admissions', 1);
    }

    public function test_receptionist_can_discharge_a_patient_and_readmit_them_later(): void
    {
        $receptionist = $this->createUser('Resepsionis');
        $patientId = $this->createPatient();
        $admissionId = $this->createAdmission($patientId);

        $this->actingAs($receptionist)
            ->post(route('staff.inpatient-admissions.discharge', $admissionId))
            ->assertRedirect(route('staff.inpatient-admissions.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('inpatient_admissions', [
            'id' => $admissionId,
            'discharged_by_user_id' => $receptionist->id,
        ]);
        self::assertNotNull(DB::table('inpatient_admissions')->where('id', $admissionId)->value('discharged_at'));

        $this->post(route('staff.inpatient-admissions.store'), [
            'patient_id' => $patientId,
            'admission_reason' => 'Admisi kedua.',
        ])->assertRedirect(route('staff.inpatient-admissions.index'));

        $this->assertDatabaseCount('inpatient_admissions', 2);
    }

    public function test_nurse_can_record_vital_signs_and_see_the_live_inpatient_history(): void
    {
        $nurse = $this->createUser('Perawat');
        $patientId = $this->createPatient();
        $admissionId = $this->createAdmission($patientId);

        $this->actingAs($nurse)
            ->get(route('staff.nursing.inpatients.index'))
            ->assertOk()
            ->assertSeeText('Pasien Rawat Inap')
            ->assertSeeText('Rina Pasien');

        $this->post(route('staff.nursing.inpatients.updates.store', $admissionId), [
            'update_status' => 'Stabil',
            'notes' => 'Pasien sadar, keluhan berkurang.',
            'temperature_celsius' => '36.8',
            'blood_pressure' => '120/80',
            'pulse_rate' => '76',
            'respiratory_rate' => '18',
            'oxygen_saturation' => '98',
        ])
            ->assertRedirect(route('staff.nursing.inpatients.show', $admissionId))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('patient_care_updates', [
            'appointment_id' => null,
            'patient_id' => $patientId,
            'inpatient_admission_id' => $admissionId,
            'recorded_by_user_id' => $nurse->id,
            'update_status' => 'Stabil',
            'temperature_celsius' => 36.8,
            'blood_pressure' => '120/80',
            'pulse_rate' => 76,
            'respiratory_rate' => 18,
            'oxygen_saturation' => 98,
            'notes' => 'Pasien sadar, keluhan berkurang.',
        ]);

        $this->get(route('staff.nursing.inpatients.updates', $admissionId))
            ->assertOk()
            ->assertSeeText('Pasien sadar, keluhan berkurang.')
            ->assertSeeText('120/80');

        $this->get(route('staff.dashboard'))
            ->assertOk()
            ->assertSeeText('Pembaruan pasien rawat inap')
            ->assertSeeText('Pasien sadar, keluhan berkurang.');
    }

    public function test_nurse_cannot_record_updates_for_a_discharged_patient(): void
    {
        $nurse = $this->createUser('Perawat');
        $patientId = $this->createPatient();
        $admissionId = $this->createAdmission($patientId, discharged: true);

        $this->actingAs($nurse)
            ->get(route('staff.nursing.inpatients.show', $admissionId))
            ->assertNotFound();

        $this->post(route('staff.nursing.inpatients.updates.store', $admissionId), [
            'update_status' => 'Stabil',
            'notes' => 'Tidak boleh dicatat setelah pasien keluar.',
        ])->assertSessionHasErrors('admission');

        $this->assertDatabaseCount('patient_care_updates', 0);
    }

    public function test_non_nurse_cannot_access_nursing_routes(): void
    {
        $receptionist = $this->createUser('Resepsionis');

        $this->actingAs($receptionist)
            ->get(route('staff.nursing.inpatients.index'))
            ->assertForbidden();
    }

    public function test_nurse_cannot_admit_or_discharge_patients(): void
    {
        $nurse = $this->createUser('Perawat');
        $patientId = $this->createPatient();
        $admissionId = $this->createAdmission($patientId);

        $this->actingAs($nurse)
            ->post(route('staff.inpatient-admissions.store'), [
                'patient_id' => $patientId,
                'admission_reason' => 'Tidak berwenang.',
            ])
            ->assertForbidden();

        $this->post(route('staff.inpatient-admissions.discharge', $admissionId))
            ->assertForbidden();

        $this->assertDatabaseCount('inpatient_admissions', 1);
    }

    public function test_administrator_can_create_a_nurse_account(): void
    {
        DB::table('roles')->insert(['role_name' => 'Perawat']);
        $administrator = $this->createUser('Admin');

        $this->actingAs($administrator)
            ->get(route('staff.management.nurses.index'))
            ->assertOk()
            ->assertSeeText('Buat akun perawat');

        $this->post(route('staff.management.nurses.store'), [
            'email' => 'perawat.baru@example.com',
            'phone_number' => '081299998888',
            'password' => 'password-rahasia-12',
            'password_confirmation' => 'password-rahasia-12',
        ])
            ->assertRedirect(route('staff.management.nurses.index'))
            ->assertSessionHas('success');

        $nurseRoleId = DB::table('roles')->where('role_name', 'Perawat')->value('id');
        $this->assertDatabaseHas('users', [
            'email' => 'perawat.baru@example.com',
            'role_id' => $nurseRoleId,
            'is_active' => true,
        ]);

        self::assertTrue(Hash::check(
            'password-rahasia-12',
            DB::table('users')->where('email', 'perawat.baru@example.com')->value('password_hash'),
        ));

        auth()->logout();
        $this->post(route('login.attempt'), [
            'email' => 'perawat.baru@example.com',
            'password' => 'password-rahasia-12',
        ])->assertRedirect(route('staff.dashboard'));

        $this->get(route('staff.nursing.inpatients.index'))->assertOk();
    }

    public function test_nurse_cannot_submit_out_of_range_vital_signs(): void
    {
        $nurse = $this->createUser('Perawat');
        $patientId = $this->createPatient();
        $admissionId = $this->createAdmission($patientId);

        $this->actingAs($nurse)
            ->post(route('staff.nursing.inpatients.updates.store', $admissionId), [
                'update_status' => 'Stabil',
                'notes' => 'Tanda vital uji.',
                'temperature_celsius' => 50,
                'blood_pressure' => 'tinggi',
                'oxygen_saturation' => 120,
            ])
            ->assertSessionHasErrors(['temperature_celsius', 'blood_pressure', 'oxygen_saturation']);

        $this->assertDatabaseCount('patient_care_updates', 0);
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

    private function createPatient(): int
    {
        return DB::table('patient_profiles')->insertGetId([
            'medical_record_number' => 'RM-00000091',
            'full_name' => 'Rina Pasien',
            'gender' => 'P',
            'birth_date' => '1992-06-15',
        ]);
    }

    private function createAdmission(int $patientId, bool $discharged = false): int
    {
        $admissionUserId = $this->createUser('Resepsionis')->id;

        return DB::table('inpatient_admissions')->insertGetId([
            'patient_id' => $patientId,
            'room_number' => '201',
            'bed_number' => 'B',
            'admission_reason' => 'Observasi.',
            'admitted_by_user_id' => $admissionUserId,
            'admitted_at' => now(),
            'discharged_by_user_id' => $discharged ? $admissionUserId : null,
            'discharged_at' => $discharged ? now() : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
