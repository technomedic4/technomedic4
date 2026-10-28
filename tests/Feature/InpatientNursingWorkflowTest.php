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

        Schema::create('inpatient_admissions', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('patient_id');
            $table->unsignedInteger('appointment_id')->nullable()->unique();
            $table->string('room_number', 30)->nullable();
            $table->string('bed_number', 30)->nullable();
            $table->text('admission_reason');
            $table->unsignedInteger('admitted_by_user_id');
            $table->timestamp('admitted_at');
            $table->unsignedInteger('discharged_by_user_id')->nullable();
            $table->timestamp('discharged_at')->nullable();
            $table->timestamps();
        });

        Schema::create('bills', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('bill_number')->unique();
            $table->unsignedInteger('patient_id');
            $table->unsignedBigInteger('total_amount');
            $table->unsignedBigInteger('amount_paid')->default(0);
            $table->string('status');
            $table->unsignedInteger('created_by_user_id');
            $table->timestamps();
        });

        Schema::create('bill_items', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('bill_id');
            $table->string('item_name');
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('unit_price');
            $table->timestamps();
        });

        Schema::create('inpatient_nursing_treatments', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('admission_id');
            $table->unsignedInteger('patient_id');
            $table->string('treatment_name', 150);
            $table->text('notes')->nullable();
            $table->unsignedInteger('recorded_by_user_id');
            $table->unsignedInteger('bill_item_id')->nullable()->unique();
            $table->timestamps();
        });

        Schema::create('patient_care_updates', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('appointment_id')->nullable()->unique();
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

    public function test_receptionist_cannot_create_an_admission_without_a_doctor_decision(): void
    {
        $receptionist = $this->createUser('Resepsionis');

        $this->actingAs($receptionist)
            ->get(route('staff.inpatient-admissions.index'))
            ->assertOk()
            ->assertSeeText('Pasien rawat inap');

        $this->get('/staff/inpatient-admissions/create')
            ->assertNotFound();

        $this->post('/staff/inpatient-admissions', [
            'patient_id' => $this->createPatient(),
            'admission_reason' => 'Tidak ada keputusan dokter.',
        ])->assertMethodNotAllowed();

        $this->assertDatabaseCount('inpatient_admissions', 0);
    }

    public function test_receptionist_can_assign_a_room_and_discharge_an_admission(): void
    {
        $receptionist = $this->createUser('Resepsionis');
        $patientId = $this->createPatient();
        $admissionId = $this->createAdmission($patientId);

        $this->actingAs($receptionist)
            ->post(route('staff.inpatient-admissions.room.assign', $admissionId), [
                'room_number' => '201',
                'bed_number' => 'B',
            ])
            ->assertRedirect(route('staff.inpatient-admissions.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('inpatient_admissions', [
            'id' => $admissionId,
            'room_number' => '201',
            'bed_number' => 'B',
        ]);

        $this->post(route('staff.inpatient-admissions.room.assign', $admissionId), [
            'room_number' => '',
            'bed_number' => 'B',
        ])->assertSessionHasErrors('room_number');

        $this->post(route('staff.inpatient-admissions.room.assign', $admissionId), [
            'room_number' => '201',
            'bed_number' => '',
        ])->assertSessionHasErrors('bed_number');

        $this->post(route('staff.inpatient-admissions.discharge', $admissionId))
            ->assertRedirect(route('staff.inpatient-admissions.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('inpatient_admissions', [
            'id' => $admissionId,
            'discharged_by_user_id' => $receptionist->id,
        ]);
        self::assertNotNull(DB::table('inpatient_admissions')->where('id', $admissionId)->value('discharged_at'));

        $this->assertDatabaseCount('inpatient_admissions', 1);
    }

    public function test_receptionist_cannot_create_a_readmission_manually_after_discharge(): void
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

        $this->post('/staff/inpatient-admissions', [
            'patient_id' => $patientId,
            'admission_reason' => 'Admisi kedua.',
        ])->assertMethodNotAllowed();

        $this->assertDatabaseCount('inpatient_admissions', 1);
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
            ->assertSeeText('Pembaruan kondisi pasien')
            ->assertSeeText('Pasien sadar, keluhan berkurang.');
    }

    public function test_checked_out_inpatient_updates_disappear_from_the_nurse_dashboard_and_live_refresh(): void
    {
        $nurse = $this->createUser('Perawat');
        $receptionist = $this->createUser('Resepsionis');
        $activePatientId = $this->createPatient();
        $checkedOutPatientId = $this->createPatient();
        $activeAdmissionId = $this->createAdmission($activePatientId);
        $checkedOutAdmissionId = $this->createAdmission($checkedOutPatientId);

        DB::table('patient_care_updates')->insert([
            [
                'appointment_id' => null,
                'patient_id' => $activePatientId,
                'inpatient_admission_id' => $activeAdmissionId,
                'recorded_by_user_id' => $nurse->id,
                'update_status' => 'Stabil',
                'notes' => 'Catatan pasien masih dirawat.',
                'created_at' => now(),
            ],
            [
                'appointment_id' => null,
                'patient_id' => $checkedOutPatientId,
                'inpatient_admission_id' => $checkedOutAdmissionId,
                'recorded_by_user_id' => $nurse->id,
                'update_status' => 'Membaik',
                'notes' => 'Catatan pasien akan checkout.',
                'created_at' => now(),
            ],
        ]);

        $this->actingAs($nurse)
            ->get(route('staff.dashboard'))
            ->assertOk()
            ->assertSee(route('staff.nursing.billing.index'))
            ->assertSeeText('Catatan pasien masih dirawat.')
            ->assertSeeText('Catatan pasien akan checkout.');

        $this->actingAs($receptionist)
            ->post(route('staff.inpatient-admissions.discharge', $checkedOutAdmissionId))
            ->assertRedirect(route('staff.inpatient-admissions.index'))
            ->assertSessionHas('success');

        $this->actingAs($nurse)
            ->get(route('staff.dashboard'))
            ->assertOk()
            ->assertSeeText('Catatan pasien masih dirawat.')
            ->assertDontSeeText('Catatan pasien akan checkout.');

        $this->get(route('staff.nursing.updates'))
            ->assertOk()
            ->assertSeeText('Catatan pasien masih dirawat.')
            ->assertDontSeeText('Catatan pasien akan checkout.');

        $this->assertDatabaseHas('patient_care_updates', [
            'inpatient_admission_id' => $checkedOutAdmissionId,
            'notes' => 'Catatan pasien akan checkout.',
        ]);
    }

    public function test_nurse_can_record_treatments_and_view_patient_billing_summary_without_controls(): void
    {
        $nurse = $this->createUser('Perawat');
        $receptionist = $this->createUser('Resepsionis');
        $patientId = $this->createPatient();
        $admissionId = $this->createAdmission($patientId);
        $billId = DB::table('bills')->insertGetId([
            'bill_number' => 'INV-INPATIENT-001',
            'patient_id' => $patientId,
            'total_amount' => 120000,
            'amount_paid' => 30000,
            'status' => 'Partially Paid',
            'created_by_user_id' => $receptionist->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('bill_items')->insert([
            'bill_id' => $billId,
            'item_name' => 'Rawat inap',
            'quantity' => 1,
            'unit_price' => 120000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($nurse)
            ->post(route('staff.nursing.inpatients.treatments.store', $admissionId), [
                'treatment_name' => 'Perawatan luka',
                'treatment_notes' => 'Luka dibersihkan dan ditutup perban.',
            ])
            ->assertRedirect(route('staff.nursing.inpatients.show', $admissionId))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('inpatient_nursing_treatments', [
            'admission_id' => $admissionId,
            'patient_id' => $patientId,
            'treatment_name' => 'Perawatan luka',
            'notes' => 'Luka dibersihkan dan ditutup perban.',
            'recorded_by_user_id' => $nurse->id,
            'bill_item_id' => null,
        ]);

        $this->get(route('staff.nursing.inpatients.show', $admissionId))
            ->assertOk()
            ->assertSeeText('Perawatan luka')
            ->assertSeeText('Menunggu validasi resepsionis')
            ->assertSeeText('INV-INPATIENT-001')
            ->assertSeeText('Rp 120.000')
            ->assertSeeText('Rp 30.000')
            ->assertSeeText('Rp 90.000')
            ->assertSeeText('Perawat tidak dapat mengubah nominal atau memproses pembayaran')
            ->assertDontSeeText('Catat pembayaran')
            ->assertDontSeeText('Edit tagihan');
    }

    public function test_nurse_records_initial_vitals_for_a_checked_in_patient_before_the_doctor_queue(): void
    {
        $nurse = $this->createUser('Perawat');
        $patientId = $this->createPatient();
        $appointmentId = $this->createInPersonAppointment($patientId, 'Checked-In');

        $this->actingAs($nurse)
            ->get(route('staff.nursing.assessment-queue.index'))
            ->assertOk()
            ->assertSeeText('Rina Pasien')
            ->assertSeeText('Antrean pasien check-in');

        $this->get(route('staff.nursing.assessment-queue.show', $appointmentId))
            ->assertOk()
            ->assertSeeText('Dokter akan menentukan tindak lanjut')
            ->assertSeeText('Catat pemeriksaan awal');

        $this->post(route('staff.nursing.assessment-queue.assessments.store', $appointmentId), [
            'update_status' => 'Stabil',
            'notes' => 'Pasien sadar dan dapat berkomunikasi.',
            'temperature_celsius' => '36.8',
            'blood_pressure' => '120/80',
            'pulse_rate' => '76',
            'respiratory_rate' => '18',
            'oxygen_saturation' => '98',
        ])
            ->assertRedirect(route('staff.nursing.assessment-queue.show', $appointmentId))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('patient_care_updates', [
            'appointment_id' => $appointmentId,
            'patient_id' => $patientId,
            'inpatient_admission_id' => null,
            'recorded_by_user_id' => $nurse->id,
            'update_status' => 'Stabil',
            'temperature_celsius' => 36.8,
            'blood_pressure' => '120/80',
            'pulse_rate' => 76,
            'respiratory_rate' => 18,
            'oxygen_saturation' => 98,
            'notes' => 'Pasien sadar dan dapat berkomunikasi.',
        ]);
        $this->assertDatabaseCount('inpatient_admissions', 0);

        $this->get(route('staff.nursing.assessment-queue.show', $appointmentId))
            ->assertOk()
            ->assertSeeText('Siap diperiksa dokter')
            ->assertSeeText('Pemeriksaan awal tersimpan')
            ->assertSeeText('120/80');

        $this->get(route('staff.nursing.assessment-queue.index'))
            ->assertOk()
            ->assertDontSeeText('Rina Pasien');

        $this->get(route('staff.dashboard'))
            ->assertOk()
            ->assertSeeText('Pembaruan kondisi pasien')
            ->assertSeeText('Pasien sadar dan dapat berkomunikasi.');

        $this->post(route('staff.nursing.assessment-queue.assessments.store', $appointmentId), [
            'update_status' => 'Stabil',
            'notes' => 'Asesmen duplikat.',
            'temperature_celsius' => '36.8',
        ])->assertSessionHasErrors('appointment');

        $this->assertDatabaseCount('patient_care_updates', 1);
    }

    public function test_nurse_cannot_assess_before_check_in_or_without_a_vital_sign(): void
    {
        $nurse = $this->createUser('Perawat');
        $patientId = $this->createPatient();
        $appointmentId = $this->createInPersonAppointment($patientId, 'Pending');

        $this->actingAs($nurse)
            ->get(route('staff.nursing.assessment-queue.show', $appointmentId))
            ->assertNotFound();

        $this->post(route('staff.nursing.assessment-queue.assessments.store', $appointmentId), [
            'update_status' => 'Stabil',
            'notes' => 'Pasien menunggu check-in.',
            'temperature_celsius' => '36.8',
        ])->assertSessionHasErrors('appointment');

        $checkedInAppointmentId = $this->createInPersonAppointment($patientId, 'Checked-In');

        $this->post(route('staff.nursing.assessment-queue.assessments.store', $checkedInAppointmentId), [
            'update_status' => 'Stabil',
            'notes' => 'Belum ada tanda vital.',
        ])->assertSessionHasErrors('temperature_celsius');

        $this->assertDatabaseCount('patient_care_updates', 0);
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

        $this->post(route('staff.nursing.inpatients.treatments.store', $admissionId), [
            'treatment_name' => 'Perawatan luka',
        ])->assertSessionHasErrors('admission');

        $this->assertDatabaseCount('patient_care_updates', 0);
        $this->assertDatabaseCount('inpatient_nursing_treatments', 0);
    }

    public function test_non_nurse_cannot_access_nursing_routes(): void
    {
        $receptionist = $this->createUser('Resepsionis');

        $this->actingAs($receptionist)
            ->get(route('staff.nursing.inpatients.index'))
            ->assertForbidden();

        $this->get(route('staff.nursing.assessment-queue.index'))
            ->assertForbidden();
    }

    public function test_nurse_cannot_admit_or_discharge_patients(): void
    {
        $nurse = $this->createUser('Perawat');
        $patientId = $this->createPatient();
        $admissionId = $this->createAdmission($patientId);

        $this->actingAs($nurse)
            ->post('/staff/inpatient-admissions', [
                'patient_id' => $patientId,
                'admission_reason' => 'Tidak berwenang.',
            ])
            ->assertMethodNotAllowed();

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

    private function createInPersonAppointment(int $patientId, string $status): int
    {
        $doctorUser = $this->createUser('Dokter');
        $departmentId = DB::table('departments')->insertGetId(['name' => 'Poli Umum']);
        $doctorId = DB::table('doctors')->insertGetId([
            'user_id' => $doctorUser->id,
            'department_id' => $departmentId,
            'full_name' => 'Dr. Sehat',
        ]);
        $scheduleId = DB::table('doctor_schedules')->insertGetId([
            'doctor_id' => $doctorId,
            'day_of_week' => 'Senin',
            'start_time' => '09:00:00',
            'end_time' => '10:00:00',
            'quota_limit' => 10,
        ]);

        return DB::table('appointments')->insertGetId([
            'booking_code' => 'REG-'.strtoupper(bin2hex(random_bytes(4))),
            'patient_id' => $patientId,
            'doctor_schedule_id' => $scheduleId,
            'appointment_date' => now()->toDateString(),
            'queue_number' => 1,
            'initial_complaint' => 'Demam sejak kemarin.',
            'consultation_type' => 'In-Person',
            'status' => $status,
            'created_at' => now(),
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
