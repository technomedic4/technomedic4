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
            $table->string('specialization')->nullable();
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
            $table->string('disposition', 20)->nullable();
            $table->unsignedInteger('referral_department_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
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

    public function test_doctor_sees_only_their_own_daily_patients(): void
    {
        [$doctor, $scheduleId] = $this->createDoctor('Dr. Utama');
        [, $otherScheduleId] = $this->createDoctor('Dr. Lain');
        $ownAppointmentId = $this->createAppointment($scheduleId, 'Checked-In', 'Pasien Jadwal Saya');
        $otherAppointmentId = $this->createAppointment($otherScheduleId, 'Checked-In', 'Pasien Dokter Lain');
        $this->createNursingAssessment($ownAppointmentId);

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
        $this->createNursingAssessment($appointmentId);

        $this->actingAs($doctor->user)
            ->post(route('staff.doctor.appointments.consultation.store', $appointmentId), [
                'diagnosis' => 'Infeksi saluran pernapasan atas',
                'treatment_plan' => 'Istirahat dan terapi sesuai pemeriksaan.',
                'disposition' => 'Rawat Jalan',
                'referral_department_id' => 'none',
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
            'disposition' => 'Rawat Jalan',
            'referral_department_id' => null,
            'notes' => 'Kontrol kembali jika keluhan menetap.',
        ]);
        $this->assertDatabaseHas('appointments', [
            'id' => $appointmentId,
            'status' => 'Completed',
        ]);
        $this->assertDatabaseCount('inpatient_admissions', 0);

        $this->get(route('staff.doctor.appointments.show', $appointmentId))
            ->assertOk()
            ->assertSeeText('Catatan konsultasi tersimpan')
            ->assertSeeText('Pemeriksaan awal perawat')
            ->assertSeeText('Tanda vital dari pemeriksaan perawat.')
            ->assertSeeText('Infeksi saluran pernapasan atas')
            ->assertSeeText('Istirahat dan terapi sesuai pemeriksaan.');

        $this->post(route('staff.doctor.appointments.consultation.store', $appointmentId), [
            'diagnosis' => 'Diagnosis kedua',
            'treatment_plan' => 'Rencana kedua',
            'disposition' => 'Rawat Jalan',
            'referral_department_id' => 'none',
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
            'disposition' => 'Rawat Jalan',
            'referral_department_id' => 'none',
        ])->assertSessionHasErrors('appointment');

        $this->assertDatabaseCount('doctor_consultations', 0);
        $this->assertDatabaseHas('appointments', [
            'id' => $appointmentId,
            'status' => 'Pending',
        ]);
    }

    public function test_doctor_cannot_open_or_record_a_checked_in_patient_before_nurse_assessment(): void
    {
        [$doctor, $scheduleId] = $this->createDoctor('Dr. Utama');
        $appointmentId = $this->createAppointment($scheduleId, 'Checked-In', 'Pasien Menunggu Perawat');

        $this->actingAs($doctor->user)
            ->get(route('staff.doctor.appointments.index'))
            ->assertOk()
            ->assertSeeText('Menunggu pemeriksaan perawat')
            ->assertDontSee(route('staff.doctor.appointments.show', $appointmentId), false);

        $this->get(route('staff.dashboard'))
            ->assertOk()
            ->assertSeeText('Menunggu perawat');

        $this->get(route('staff.doctor.appointments.show', $appointmentId))
            ->assertForbidden();

        $this->post(route('staff.doctor.appointments.consultation.store', $appointmentId), [
            'diagnosis' => 'Diagnosis uji',
            'treatment_plan' => 'Rencana terapi uji',
            'disposition' => 'Rawat Jalan',
            'referral_department_id' => 'none',
        ])->assertSessionHasErrors('appointment');

        $this->assertDatabaseCount('doctor_consultations', 0);
        $this->assertDatabaseHas('appointments', [
            'id' => $appointmentId,
            'status' => 'Checked-In',
        ]);
    }

    public function test_doctor_can_open_the_appointment_after_a_nurse_assessment_is_saved(): void
    {
        [$doctor, $scheduleId] = $this->createDoctor('Dr. Utama');
        $appointmentId = $this->createAppointment($scheduleId, 'Checked-In', 'Pasien Siap Diperiksa');
        $this->createNursingAssessment($appointmentId);

        $this->actingAs($doctor->user)
            ->get(route('staff.doctor.appointments.show', $appointmentId))
            ->assertOk()
            ->assertSeeText('Pemeriksaan awal perawat')
            ->assertSeeText('Tanda vital dari pemeriksaan perawat.');
    }

    public function test_doctor_can_view_a_completed_legacy_consultation_without_a_nurse_assessment(): void
    {
        [$doctor, $scheduleId] = $this->createDoctor('Dr. Utama');
        $appointmentId = $this->createAppointment($scheduleId, 'Completed', 'Pasien Kunjungan Lama');
        DB::table('doctor_consultations')->insert([
            'appointment_id' => $appointmentId,
            'patient_id' => DB::table('appointments')->where('id', $appointmentId)->value('patient_id'),
            'doctor_id' => $doctor->id,
            'diagnosis' => 'Diagnosis kunjungan lama.',
            'treatment_plan' => 'Rencana lama.',
            'disposition' => null,
            'referral_department_id' => null,
            'notes' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($doctor->user)
            ->get(route('staff.doctor.appointments.show', $appointmentId))
            ->assertOk()
            ->assertSeeText('Data asesmen awal tidak tersedia untuk kunjungan lama.')
            ->assertSeeText('Diagnosis kunjungan lama.');
    }

    public function test_doctor_can_choose_inpatient_and_create_an_admission_for_the_checked_in_patient(): void
    {
        [$doctor, $scheduleId] = $this->createDoctor('Dr. Utama');
        $appointmentId = $this->createAppointment($scheduleId, 'Checked-In', 'Pasien Rawat Inap');
        $this->createNursingAssessment($appointmentId);

        $this->actingAs($doctor->user)
            ->post(route('staff.doctor.appointments.consultation.store', $appointmentId), [
                'diagnosis' => 'Perlu observasi lanjutan.',
                'treatment_plan' => 'Pemantauan di ruang rawat.',
                'disposition' => 'Rawat Inap',
                'referral_department_id' => 'none',
                'notes' => 'Pantau kondisi setelah masuk.',
            ])
            ->assertRedirect(route('staff.doctor.appointments.show', $appointmentId))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('doctor_consultations', [
            'appointment_id' => $appointmentId,
            'disposition' => 'Rawat Inap',
            'diagnosis' => 'Perlu observasi lanjutan.',
        ]);
        $this->assertDatabaseHas('inpatient_admissions', [
            'appointment_id' => $appointmentId,
            'patient_id' => DB::table('appointments')->where('id', $appointmentId)->value('patient_id'),
            'admitted_by_user_id' => $doctor->user->id,
            'room_number' => null,
            'bed_number' => null,
            'admission_reason' => 'Perlu observasi lanjutan.',
            'discharged_at' => null,
        ]);
        $this->assertDatabaseHas('appointments', [
            'id' => $appointmentId,
            'status' => 'Completed',
        ]);

        $this->get(route('staff.doctor.appointments.show', $appointmentId))
            ->assertOk()
            ->assertSeeText('Rawat Inap');
    }

    public function test_general_practitioner_can_record_a_specialist_referral(): void
    {
        [$doctor, $scheduleId] = $this->createDoctor('Dr. Utama');
        $specialistDepartmentId = DB::table('departments')->insertGetId(['name' => 'Kardiologi']);
        $specialistUser = $this->createUser('Dokter');
        DB::table('doctors')->insert([
            'user_id' => $specialistUser->id,
            'department_id' => $specialistDepartmentId,
            'full_name' => 'Dr. Spesialis',
            'specialization' => 'Spesialis Jantung dan Pembuluh Darah',
        ]);
        $appointmentId = $this->createAppointment($scheduleId, 'Checked-In', 'Pasien Rujukan');
        $this->createNursingAssessment($appointmentId);

        $this->actingAs($doctor->user)
            ->get(route('staff.doctor.appointments.show', $appointmentId))
            ->assertOk()
            ->assertSeeText('Rujukan ke dokter spesialis')
            ->assertSeeText('Kardiologi');

        $this->post(route('staff.doctor.appointments.consultation.store', $appointmentId), [
            'diagnosis' => 'Perlu evaluasi jantung.',
            'treatment_plan' => 'Pemeriksaan lanjutan.',
            'disposition' => 'Rawat Jalan',
            'referral_department_id' => (string) $specialistDepartmentId,
        ])
            ->assertRedirect(route('staff.doctor.appointments.show', $appointmentId))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('doctor_consultations', [
            'appointment_id' => $appointmentId,
            'referral_department_id' => $specialistDepartmentId,
        ]);

        $this->get(route('staff.doctor.appointments.show', $appointmentId))
            ->assertOk()
            ->assertSeeText('Rujukan spesialis')
            ->assertSeeText('Kardiologi');
    }

    public function test_general_practitioner_cannot_refer_to_a_department_without_a_specialist(): void
    {
        [$doctor, $scheduleId] = $this->createDoctor('Dr. Utama');
        $otherGeneralDepartmentId = DB::table('departments')->insertGetId(['name' => 'Poli Umum Lain']);
        $generalUser = $this->createUser('Dokter');
        DB::table('doctors')->insert([
            'user_id' => $generalUser->id,
            'department_id' => $otherGeneralDepartmentId,
            'full_name' => 'Dr. Umum Lain',
            'specialization' => 'Dokter Umum',
        ]);
        $appointmentId = $this->createAppointment($scheduleId, 'Checked-In', 'Pasien Rujukan Tidak Valid');
        $this->createNursingAssessment($appointmentId);

        $this->actingAs($doctor->user)
            ->post(route('staff.doctor.appointments.consultation.store', $appointmentId), [
                'diagnosis' => 'Diagnosis pasien.',
                'treatment_plan' => 'Rencana pemeriksaan.',
                'disposition' => 'Rawat Jalan',
                'referral_department_id' => (string) $otherGeneralDepartmentId,
            ])
            ->assertSessionHasErrors('referral_department_id');

        $this->assertDatabaseCount('doctor_consultations', 0);
    }

    public function test_doctor_cannot_save_consultation_without_a_valid_disposition(): void
    {
        [$doctor, $scheduleId] = $this->createDoctor('Dr. Utama');
        $appointmentId = $this->createAppointment($scheduleId, 'Checked-In', 'Pasien Tanpa Keputusan');
        $this->createNursingAssessment($appointmentId);

        $this->actingAs($doctor->user)
            ->post(route('staff.doctor.appointments.consultation.store', $appointmentId), [
                'diagnosis' => 'Diagnosis pasien.',
                'treatment_plan' => 'Rencana pemeriksaan.',
                'disposition' => 'Pulang saja',
                'referral_department_id' => 'none',
            ])
            ->assertSessionHasErrors('disposition');

        $this->assertDatabaseCount('doctor_consultations', 0);
        $this->assertDatabaseCount('inpatient_admissions', 0);
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
            'disposition' => 'Rawat Jalan',
            'referral_department_id' => 'none',
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
            'specialization' => 'Dokter Umum',
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

    private function createNursingAssessment(int $appointmentId): void
    {
        $patientId = DB::table('appointments')->where('id', $appointmentId)->value('patient_id');

        DB::table('patient_care_updates')->insert([
            'appointment_id' => $appointmentId,
            'patient_id' => $patientId,
            'inpatient_admission_id' => null,
            'recorded_by_user_id' => null,
            'update_status' => 'Stabil',
            'temperature_celsius' => 36.8,
            'blood_pressure' => '120/80',
            'pulse_rate' => 76,
            'respiratory_rate' => 18,
            'oxygen_saturation' => 98,
            'notes' => 'Tanda vital dari pemeriksaan perawat.',
            'created_at' => now(),
        ]);
    }
}
