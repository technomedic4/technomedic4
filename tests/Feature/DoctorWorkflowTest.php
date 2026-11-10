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
            $table->unsignedInteger('referral_consultation_id')->nullable();
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
            $table->integer('referral_doctor_id')->nullable();
            $table->text('referral_reason')->nullable();
            $table->text('examination_findings')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('laboratory_orders', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('consultation_id')->unique();
            $table->unsignedInteger('patient_id');
            $table->unsignedInteger('referring_doctor_id');
            $table->text('requested_tests');
            $table->json('requested_test_codes')->nullable();
            $table->text('clinical_notes')->nullable();
            $table->string('status', 20)->default('Pending');
            $table->text('results')->nullable();
            $table->text('result_notes')->nullable();
            $table->unsignedInteger('resulted_by_user_id')->nullable();
            $table->timestamp('resulted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('laboratory_results', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('laboratory_order_id');
            $table->string('parameter_code', 60);
            $table->string('parameter_name', 150);
            $table->string('result_value', 100);
            $table->string('unit', 40)->nullable();
            $table->string('reference_range', 100)->nullable();
            $table->string('interpretation', 20)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
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
            $table->unsignedInteger('doctor_discharge_approved_by_user_id')->nullable();
            $table->timestamp('doctor_discharge_approved_at')->nullable();
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
        $this->validatePatientCondition($doctor, $appointmentId);

        $this->actingAs($doctor->user)
            ->get(route('staff.doctor.appointments.show', $appointmentId))
            ->assertOk()
            ->assertSeeText('Pemeriksaan Darah')
            ->assertSeeText('Pemeriksaan Urine')
            ->assertSeeText('Hematologi / Darah Lengkap')
            ->assertSeeText('Kimia Darah')
            ->assertSeeText('Hemoglobin (Hb)')
            ->assertSeeText('Hematokrit (HCT)')
            ->assertSeeText('Eritrosit (RBC)')
            ->assertSeeText('Leukosit (WBC)')
            ->assertSeeText('Trombosit (PLT)')
            ->assertSeeText('MCV')
            ->assertSeeText('MCH')
            ->assertSeeText('MCHC')
            ->assertSeeText('Glukosa darah')
            ->assertSeeText('Ureum')
            ->assertSeeText('Kreatinin')
            ->assertSeeText('Asam urat')
            ->assertSeeText('Kolesterol total')
            ->assertSeeText('Trigliserida');

        $this->actingAs($doctor->user)
            ->post(route('staff.doctor.appointments.consultation.store', $appointmentId), [
                'diagnosis' => 'Infeksi saluran pernapasan atas',
                'treatment_plan' => 'Istirahat dan terapi sesuai pemeriksaan.',
                'disposition' => 'Rawat Jalan',
                'referral_slot' => 'none',
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
            'referral_doctor_id' => null,
            'referral_reason' => null,
            'examination_findings' => null,
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
            ->assertSeeText('Kondisi pasien & tanda vital')
            ->assertSeeText('Kondisi pasien telah diperiksa dokter.')
            ->assertSeeText('Infeksi saluran pernapasan atas')
            ->assertSeeText('Istirahat dan terapi sesuai pemeriksaan.');

        $this->post(route('staff.doctor.appointments.consultation.store', $appointmentId), [
            'diagnosis' => 'Diagnosis kedua',
            'treatment_plan' => 'Rencana kedua',
            'disposition' => 'Rawat Jalan',
            'referral_slot' => 'none',
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
            'referral_slot' => 'none',
        ])->assertSessionHasErrors('appointment');

        $this->assertDatabaseCount('doctor_consultations', 0);
        $this->assertDatabaseHas('appointments', [
            'id' => $appointmentId,
            'status' => 'Pending',
        ]);
    }

    public function test_laboratory_result_returns_to_referring_doctor_as_a_printable_report(): void
    {
        [$doctor, $scheduleId] = $this->createDoctor('Dr. Utama');
        $appointmentId = $this->createAppointment($scheduleId, 'Checked-In', 'Pasien Laboratorium');
        $this->createNursingAssessment($appointmentId);
        $this->validatePatientCondition($doctor, $appointmentId);

        $this->actingAs($doctor->user)
            ->post(route('staff.doctor.appointments.consultation.store', $appointmentId), [
                'diagnosis' => 'Anemia perlu dievaluasi.',
                'treatment_plan' => 'Evaluasi lanjutan setelah hasil laboratorium.',
                'disposition' => 'Rawat Jalan',
                'referral_slot' => 'none',
                'laboratory_requested' => '1',
                'requested_test_codes' => ['hemoglobin', 'blood_glucose', 'urine_ph'],
                'laboratory_notes' => "Evaluasi kemungkinan anemia.\n<script>alert('note')</script>",
            ])
            ->assertRedirect(route('staff.doctor.appointments.show', $appointmentId))
            ->assertSessionHas('success');

        $consultationId = DB::table('doctor_consultations')->where('appointment_id', $appointmentId)->value('id');
        $labOrderId = (int) DB::table('laboratory_orders')
            ->where('consultation_id', $consultationId)
            ->value('id');

        $this->assertDatabaseHas('laboratory_orders', [
            'id' => $labOrderId,
            'patient_id' => DB::table('appointments')->where('id', $appointmentId)->value('patient_id'),
            'referring_doctor_id' => $doctor->id,
            'requested_tests' => "Hemoglobin (Hb)\nGlukosa darah\npH",
            'clinical_notes' => "Evaluasi kemungkinan anemia.\n<script>alert('note')</script>",
            'status' => 'Pending',
        ]);

        $laboratoryStaff = $this->createUser('Laboratorium');

        $this->actingAs($laboratoryStaff)
            ->get(route('staff.laboratory.requests.index'))
            ->assertOk()
            ->assertSeeText('Pasien Laboratorium')
            ->assertSeeText('Hemoglobin (Hb)')
            ->assertSeeText('Pemeriksaan lab darah')
            ->assertSeeText('Pemeriksaan lab urine')
            ->assertSeeText('Darah · Mulai')
            ->assertSeeText('Urine · Mulai')
            ->assertDontSee('<script>', false);

        $this->get(route('staff.laboratory.requests.blood'))
            ->assertOk()
            ->assertSeeText('Antrean pemeriksaan lab darah')
            ->assertSeeText('Pasien Laboratorium')
            ->assertSeeText('Darah · Mulai');

        $this->get(route('staff.laboratory.requests.urine'))
            ->assertOk()
            ->assertSeeText('Antrean pemeriksaan lab urine')
            ->assertSeeText('Pasien Laboratorium')
            ->assertSeeText('Urine · Mulai');

        $this->get(route('staff.laboratory.requests.show', $labOrderId))
            ->assertOk()
            ->assertSeeText('Mulai pemeriksaan')
            ->assertSeeText('Hemoglobin (Hb)')
            ->assertSeeText('Glukosa darah')
            ->assertSeeText('pH')
            ->assertSeeText('Pemeriksaan Darah')
            ->assertSeeText('Pemeriksaan Urine')
            ->assertDontSee('<script>', false);

        $this->get(route('staff.laboratory.requests.index', [
            'search' => 'Pasien Laboratorium',
            'status' => 'Pending',
        ]))
            ->assertOk()
            ->assertSeeText('Total permintaan')
            ->assertSeeText('Menunggu')
            ->assertSeeText('Pasien Laboratorium');

        $this->assertDatabaseHas('laboratory_orders', [
            'id' => $labOrderId,
            'status' => 'Pending',
        ]);

        $this->post(route('staff.laboratory.requests.start', $labOrderId), [
            'specimen' => 'blood',
        ])
            ->assertRedirect(route('staff.laboratory.requests.show', ['labOrder' => $labOrderId, 'specimen' => 'blood']))
            ->assertSessionHas('success', 'Pemeriksaan dimulai.');

        $this->assertDatabaseHas('laboratory_orders', [
            'id' => $labOrderId,
            'status' => 'In Progress',
        ]);

        $this->get(route('staff.laboratory.requests.show', $labOrderId))
            ->assertOk()
            ->assertSee('name="parameter_results[hemoglobin][value]"', false)
            ->assertSee('name="parameter_results[blood_glucose][value]"', false)
            ->assertDontSee('name="parameter_results[urine_ph][value]"', false)
            ->assertDontSee('name="parameter_results[leukocytes][value]"', false)
            ->assertSeeText('Pemeriksaan Darah')
            ->assertSeeText('Satuan')
            ->assertSeeText('Nilai rujukan')
            ->assertSeeText('Keterangan')
            ->assertSeeText('Parameter sampel lain diisi pada antrean terpisah');

        $this->post(route('staff.laboratory.requests.result.store', $labOrderId), [
            'specimen' => 'blood',
            'parameter_results' => [
                'hemoglobin' => ['value' => '12.5', 'unit' => 'g/dL', 'reference_range' => '13-17', 'interpretation' => 'Low'],
            ],
        ])->assertSessionHasErrors('parameter_results.blood_glucose.value');

        $this->assertDatabaseCount('laboratory_results', 0);

        $this->post(route('staff.laboratory.requests.result.store', $labOrderId), [
            'specimen' => 'blood',
            'parameter_results' => [
                'hemoglobin' => ['value' => '12.5', 'unit' => 'g/dL', 'reference_range' => '13-17', 'interpretation' => 'Abnormal'],
                'blood_glucose' => ['value' => '90', 'unit' => 'mg/dL', 'reference_range' => '70-100', 'interpretation' => 'Normal'],
            ],
        ])->assertSessionHasErrors('parameter_results.hemoglobin.interpretation');

        $this->assertDatabaseCount('laboratory_results', 0);

        $this->actingAs($laboratoryStaff)
            ->post(route('staff.laboratory.requests.result.store', $labOrderId), [
                'specimen' => 'blood',
                'parameter_results' => [
                    'hemoglobin' => ['value' => '12.5', 'unit' => 'g/dL', 'reference_range' => '13-17', 'interpretation' => 'Low'],
                    'blood_glucose' => ['value' => '90', 'unit' => 'mg/dL', 'reference_range' => '70-100', 'interpretation' => 'Normal'],
                ],
            ])
            ->assertRedirect(route('staff.laboratory.requests.show', ['labOrder' => $labOrderId, 'specimen' => 'blood']))
            ->assertSessionHas(
                'success',
                'Hasil sampel berhasil disimpan. Sampel lain masih perlu diperiksa sebelum hasil lengkap tersedia untuk dokter.',
            );

        $this->assertDatabaseHas('laboratory_orders', [
            'id' => $labOrderId,
            'status' => 'In Progress',
            'results' => null,
            'result_notes' => null,
            'resulted_by_user_id' => $laboratoryStaff->id,
        ]);
        $this->assertDatabaseHas('laboratory_results', [
            'laboratory_order_id' => $labOrderId,
            'parameter_code' => 'hemoglobin',
            'result_value' => '12.5',
            'unit' => 'g/dL',
            'reference_range' => '13-17',
            'interpretation' => 'Low',
        ]);
        $this->assertDatabaseHas('laboratory_results', [
            'laboratory_order_id' => $labOrderId,
            'parameter_code' => 'blood_glucose',
            'result_value' => '90',
            'unit' => 'mg/dL',
            'reference_range' => '70-100',
            'interpretation' => 'Normal',
        ]);

        $this->actingAs($laboratoryStaff)
            ->get(route('staff.laboratory.requests.show', ['labOrder' => $labOrderId, 'specimen' => 'blood']))
            ->assertOk()
            ->assertSeeText('Hasil pemeriksaan darah tersimpan')
            ->assertSeeText('12.5')
            ->assertSeeText('Lanjutkan pemeriksaan urine')
            ->assertSee(route('staff.laboratory.requests.show', ['labOrder' => $labOrderId, 'specimen' => 'urine']), false);

        $this->get(route('staff.laboratory.requests.show', ['labOrder' => $labOrderId, 'specimen' => 'urine']))
            ->assertOk()
            ->assertSeeText('Form pemeriksaan urine')
            ->assertSee('name="parameter_results[urine_ph][value]"', false)
            ->assertSee('name="parameter_results[urine_ph][reference_range]"', false)
            ->assertDontSee('name="parameter_results[urine_ph][interpretation]"', false)
            ->assertDontSee('name="parameter_results[hemoglobin][value]"', false);

        $this->post(route('staff.laboratory.requests.result.store', $labOrderId), [
            'specimen' => 'urine',
            'parameter_results' => [
                'urine_ph' => ['value' => '6.0', 'unit' => '', 'reference_range' => '5-7'],
            ],
            'result_notes' => "Hasil dikirim untuk ditinjau dokter.\n<script>alert('kesimpulan')</script>",
        ])->assertRedirect(route('staff.laboratory.requests.show', ['labOrder' => $labOrderId, 'specimen' => 'urine']))
            ->assertSessionHas(
                'success',
                'Hasil laboratorium lengkap berhasil disimpan dan telah tersedia untuk dokter perujuk.',
            );

        $this->assertDatabaseHas('laboratory_orders', [
            'id' => $labOrderId,
            'status' => 'Completed',
            'result_notes' => "Hasil dikirim untuk ditinjau dokter.\n<script>alert('kesimpulan')</script>",
        ]);
        $this->assertDatabaseHas('laboratory_results', [
            'laboratory_order_id' => $labOrderId,
            'parameter_code' => 'urine_ph',
            'result_value' => '6.0',
            'reference_range' => '5-7',
            'interpretation' => null,
        ]);

        $this->actingAs($laboratoryStaff)
            ->get(route('staff.laboratory.requests.show', ['labOrder' => $labOrderId, 'specimen' => 'blood']))
            ->assertOk()
            ->assertSeeText('Hasil pemeriksaan')
            ->assertSeeText('12.5')
            ->assertSeeText('90')
            ->assertSeeText('g/dL')
            ->assertSeeText('Pemeriksaan Darah')
            ->assertSee('&lt;script&gt;', false)
            ->assertDontSee('<script>', false);

        $this->actingAs($laboratoryStaff)
            ->post(route('staff.laboratory.requests.result.store', $labOrderId), [
                'results' => 'Perubahan yang tidak diperbolehkan.',
            ])
            ->assertSessionHasErrors('lab_order');

        $this->actingAs($doctor->user)
            ->get(route('staff.doctor.laboratory-results.index'))
            ->assertOk()
            ->assertSeeText('Pasien Laboratorium')
            ->assertSeeText('Hasil tersedia');

        $this->get(route('staff.doctor.laboratory-results.show', $labOrderId))
            ->assertOk()
            ->assertSeeText('SURAT HASIL PEMERIKSAAN LABORATORIUM')
            ->assertSeeText('12.5')
            ->assertSeeText('90')
            ->assertSeeText('6.0')
            ->assertSeeText('g/dL')
            ->assertSee('Cetak / Simpan PDF')
            ->assertSee('window.print()', false)
            ->assertSee('&lt;script&gt;', false)
            ->assertDontSee('<script>', false);

        $this->get(route('staff.doctor.appointments.show', $appointmentId))
            ->assertOk()
            ->assertSeeText('Hasil sudah dikirim')
            ->assertSeeText('12.5')
            ->assertSeeText('90')
            ->assertSeeText('6.0')
            ->assertSeeText('Pemeriksaan Urine')
            ->assertSeeText('Buka surat hasil laboratorium');

        [, $otherDoctorScheduleId] = $this->createDoctor('Dr. Lain');
        $otherDoctor = DB::table('doctors')
            ->where('id', DB::table('doctor_schedules')->where('id', $otherDoctorScheduleId)->value('doctor_id'))
            ->first();

        $this->actingAs(User::query()->findOrFail($otherDoctor->user_id))
            ->get(route('staff.doctor.laboratory-results.show', $labOrderId))
            ->assertNotFound();

        $this->actingAs($doctor->user)
            ->get(route('staff.laboratory.requests.index'))
            ->assertForbidden();

        $this->actingAs($laboratoryStaff)
            ->get(route('staff.doctor.laboratory-results.index'))
            ->assertForbidden();
    }

    public function test_doctor_must_enter_tests_when_requesting_laboratory_examination(): void
    {
        [$doctor, $scheduleId] = $this->createDoctor('Dr. Utama');
        $appointmentId = $this->createAppointment($scheduleId, 'Checked-In', 'Pasien Tanpa Pemeriksaan');
        $this->createNursingAssessment($appointmentId);
        $this->validatePatientCondition($doctor, $appointmentId);

        $this->actingAs($doctor->user)
            ->post(route('staff.doctor.appointments.consultation.store', $appointmentId), [
                'diagnosis' => 'Diagnosis pemeriksaan.',
                'treatment_plan' => 'Rencana tindak lanjut.',
                'disposition' => 'Rawat Jalan',
                'referral_slot' => 'none',
                'laboratory_requested' => '1',
            ])
            ->assertSessionHasErrors('requested_test_codes');

        $this->post(route('staff.doctor.appointments.consultation.store', $appointmentId), [
            'diagnosis' => 'Diagnosis pemeriksaan.',
            'treatment_plan' => 'Rencana tindak lanjut.',
            'disposition' => 'Rawat Jalan',
            'referral_slot' => 'none',
            'laboratory_requested' => '1',
            'requested_test_codes' => ['unknown_parameter'],
        ])->assertSessionHasErrors('requested_test_codes.0');

        $this->assertDatabaseCount('doctor_consultations', 0);
        $this->assertDatabaseCount('laboratory_orders', 0);
    }

    public function test_existing_free_text_laboratory_requests_remain_viewable_and_completable(): void
    {
        [$doctor, $scheduleId] = $this->createDoctor('Dr. Legacy');
        $appointmentId = $this->createAppointment($scheduleId, 'Completed', 'Pasien Permintaan Lama');
        $consultationId = DB::table('doctor_consultations')->insertGetId([
            'appointment_id' => $appointmentId,
            'patient_id' => DB::table('appointments')->where('id', $appointmentId)->value('patient_id'),
            'doctor_id' => $doctor->id,
            'diagnosis' => 'Pemeriksaan lama.',
            'treatment_plan' => 'Evaluasi hasil.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $labOrderId = DB::table('laboratory_orders')->insertGetId([
            'consultation_id' => $consultationId,
            'patient_id' => DB::table('appointments')->where('id', $appointmentId)->value('patient_id'),
            'referring_doctor_id' => $doctor->id,
            'requested_tests' => 'Widal dan darah',
            'requested_test_codes' => null,
            'status' => 'Pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $laboratoryStaff = $this->createUser('Laboratorium');

        $this->actingAs($laboratoryStaff)
            ->get(route('staff.laboratory.requests.show', $labOrderId))
            ->assertOk()
            ->assertSeeText('Widal dan darah')
            ->assertSeeText('Mulai pemeriksaan');

        $this->post(route('staff.laboratory.requests.start', $labOrderId))
            ->assertRedirect(route('staff.laboratory.requests.show', ['labOrder' => $labOrderId, 'specimen' => 'blood']));
        $this->post(route('staff.laboratory.requests.result.store', $labOrderId), [
            'results' => 'Hasil Widal negatif.',
        ])->assertRedirect(route('staff.laboratory.requests.show', ['labOrder' => $labOrderId, 'specimen' => 'blood']))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('laboratory_orders', [
            'id' => $labOrderId,
            'requested_test_codes' => null,
            'status' => 'Completed',
            'results' => 'Hasil Widal negatif.',
        ]);
    }

    public function test_receptionist_can_create_laboratory_staff_accounts(): void
    {
        $receptionist = $this->createUser('Resepsionis');
        DB::table('roles')->insert(['role_name' => 'Laboratorium']);

        $this->actingAs($receptionist)
            ->post(route('staff.management.laboratory.store'), [
                'email' => 'lab.staff@example.com',
                'phone_number' => '081234567891',
                'password' => 'laboratory-password',
                'password_confirmation' => 'laboratory-password',
            ])
            ->assertRedirect(route('staff.management.laboratory.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('users', [
            'email' => 'lab.staff@example.com',
            'phone_number' => '081234567891',
            'role_id' => DB::table('roles')->where('role_name', 'Laboratorium')->value('id'),
            'is_active' => true,
        ]);

        $this->actingAs($receptionist)
            ->get(route('staff.management.laboratory.index'))
            ->assertOk()
            ->assertSeeText('lab.staff@example.com');

        $laboratoryStaff = User::query()->where('email', 'lab.staff@example.com')->firstOrFail();

        $this->actingAs($laboratoryStaff)
            ->get(route('staff.dashboard'))
            ->assertRedirect(route('staff.laboratory.requests.index'));

        $this->get(route('staff.management.laboratory.index'))
            ->assertForbidden();
    }

    public function test_doctor_can_open_checked_in_patient_and_must_validate_condition_before_consultation(): void
    {
        [$doctor, $scheduleId] = $this->createDoctor('Dr. Utama');
        $appointmentId = $this->createAppointment($scheduleId, 'Checked-In', 'Pasien Menunggu Perawat');

        $this->actingAs($doctor->user)
            ->get(route('staff.doctor.appointments.index'))
            ->assertOk()
            ->assertSeeText('Menunggu tanda vital perawat')
            ->assertSee(route('staff.doctor.appointments.show', $appointmentId), false);

        $this->get(route('staff.dashboard'))
            ->assertOk()
            ->assertSeeText('Menunggu tanda vital perawat');

        $this->get(route('staff.doctor.appointments.show', $appointmentId))
            ->assertOk()
            ->assertSeeText('Validasi kondisi pasien')
            ->assertSeeText('Menunggu perawat mencatat tanda vital')
            ->assertDontSee('name="temperature_celsius"', false)
            ->assertDontSeeText('Simpan validasi kondisi');

        $this->post(route('staff.doctor.appointments.consultation.store', $appointmentId), [
            'diagnosis' => 'Diagnosis uji',
            'treatment_plan' => 'Rencana terapi uji',
            'disposition' => 'Rawat Jalan',
            'referral_slot' => 'none',
        ])->assertSessionHasErrors('appointment');

        $this->assertDatabaseCount('doctor_consultations', 0);

        $this->post(route('staff.doctor.appointments.patient-condition.store', $appointmentId), [
            'update_status' => 'Stabil',
            'notes' => 'Pasien sadar dan dapat berkomunikasi.',
        ])->assertSessionHasErrors('appointment');

        $nurse = $this->createUser('Perawat');
        $this->actingAs($nurse)
            ->post(route('staff.nursing.assessment-queue.assessments.store', $appointmentId), [
                'notes' => 'Tanda vital dari pemeriksaan perawat.',
                'temperature_celsius' => '36.8',
                'blood_pressure' => '120/80',
            ])
            ->assertRedirect(route('staff.nursing.assessment-queue.show', $appointmentId))
            ->assertSessionHas('success');

        $this->actingAs($doctor->user)
            ->post(route('staff.doctor.appointments.patient-condition.store', $appointmentId), [
                'update_status' => 'Stabil',
                'notes' => 'Pasien sadar dan dapat berkomunikasi.',
            ])
            ->assertRedirect(route('staff.doctor.appointments.show', $appointmentId))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('patient_care_updates', [
            'appointment_id' => $appointmentId,
            'recorded_by_user_id' => $doctor->user->id,
            'update_status' => 'Stabil',
            'temperature_celsius' => 36.8,
            'blood_pressure' => '120/80',
            'notes' => 'Pasien sadar dan dapat berkomunikasi.',
        ]);

        $this->assertDatabaseHas('appointments', [
            'id' => $appointmentId,
            'status' => 'Checked-In',
        ]);
    }

    public function test_doctor_can_view_an_existing_nurse_assessment_before_validating_it(): void
    {
        [$doctor, $scheduleId] = $this->createDoctor('Dr. Utama');
        $appointmentId = $this->createAppointment($scheduleId, 'Checked-In', 'Pasien Siap Diperiksa');
        $this->createNursingAssessment($appointmentId);

        $this->actingAs($doctor->user)
            ->get(route('staff.doctor.appointments.show', $appointmentId))
            ->assertOk()
            ->assertSeeText('TANDA VITAL DARI PERAWAT')
            ->assertSeeText('Tanda vital dari pemeriksaan perawat.')
            ->assertSeeText('Simpan validasi kondisi')
            ->assertDontSee('name="temperature_celsius"', false);
    }

    public function test_doctor_can_update_a_previous_assessment_and_becomes_its_validating_recorder(): void
    {
        [$doctor, $scheduleId] = $this->createDoctor('Dr. Utama');
        $appointmentId = $this->createAppointment($scheduleId, 'Checked-In', 'Pasien Perlu Validasi');
        $this->createNursingAssessment($appointmentId);

        $this->actingAs($doctor->user)
            ->post(route('staff.doctor.appointments.patient-condition.store', $appointmentId), [
                'update_status' => 'Perlu Pemantauan',
                'notes' => 'Pemantauan dokter.',
                'blood_pressure' => '125/85',
            ])
            ->assertRedirect(route('staff.doctor.appointments.show', $appointmentId))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('patient_care_updates', [
            'appointment_id' => $appointmentId,
            'recorded_by_user_id' => $doctor->user->id,
            'update_status' => 'Perlu Pemantauan',
            'blood_pressure' => '120/80',
            'notes' => 'Pemantauan dokter.',
        ]);
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
            'referral_doctor_id' => null,
            'referral_reason' => null,
            'examination_findings' => null,
            'notes' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($doctor->user)
            ->get(route('staff.doctor.appointments.show', $appointmentId))
            ->assertOk()
            ->assertSeeText('Data kondisi pasien tidak tersedia untuk kunjungan ini.')
            ->assertSeeText('Diagnosis kunjungan lama.');
    }

    public function test_doctor_can_choose_inpatient_and_create_an_admission_for_the_checked_in_patient(): void
    {
        [$doctor, $scheduleId] = $this->createDoctor('Dr. Utama');
        $appointmentId = $this->createAppointment($scheduleId, 'Checked-In', 'Pasien Rawat Inap');
        $this->createNursingAssessment($appointmentId);
        $this->validatePatientCondition($doctor, $appointmentId);

        $this->actingAs($doctor->user)
            ->post(route('staff.doctor.appointments.consultation.store', $appointmentId), [
                'diagnosis' => 'Perlu observasi lanjutan.',
                'treatment_plan' => 'Pemantauan di ruang rawat.',
                'disposition' => 'Rawat Inap',
                'referral_slot' => 'none',
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

    public function test_doctor_sees_only_assigned_inpatients_and_their_monitoring_history(): void
    {
        [$doctor, $scheduleId] = $this->createDoctor('Dr. Utama');
        [$otherDoctor, $otherScheduleId] = $this->createDoctor('Dr. Lain');
        $admissionId = $this->createDoctorAdmission($doctor, $scheduleId, 'Pasien Tanggung Jawab');
        $otherAdmissionId = $this->createDoctorAdmission($otherDoctor, $otherScheduleId, 'Pasien Dokter Lain');
        $nurse = $this->createUser('Perawat');

        DB::table('patient_care_updates')->insert([
            'appointment_id' => null,
            'patient_id' => DB::table('inpatient_admissions')->where('id', $admissionId)->value('patient_id'),
            'inpatient_admission_id' => $admissionId,
            'recorded_by_user_id' => $nurse->id,
            'update_status' => 'Perlu Pemantauan',
            'blood_pressure' => '125/85',
            'notes' => 'Pemantauan perawat untuk pasien dokter.',
            'created_at' => now(),
        ]);
        DB::table('patient_care_updates')->insert([
            'appointment_id' => null,
            'patient_id' => DB::table('inpatient_admissions')->where('id', $otherAdmissionId)->value('patient_id'),
            'inpatient_admission_id' => $otherAdmissionId,
            'recorded_by_user_id' => $nurse->id,
            'update_status' => 'Stabil',
            'notes' => 'Data pasien dokter lain.',
            'created_at' => now(),
        ]);

        $this->actingAs($doctor->user)
            ->get(route('staff.doctor.inpatients.index'))
            ->assertOk()
            ->assertSeeText('Pasien Tanggung Jawab')
            ->assertDontSeeText('Pasien Dokter Lain');

        $this->get(route('staff.doctor.inpatients.show', $admissionId))
            ->assertOk()
            ->assertSeeText('Riwayat pemantauan perawat')
            ->assertSeeText('Diagnosis dan rencana pengobatan')
            ->assertDontSee('name="update_status"', false)
            ->assertDontSee('name="temperature_celsius"', false)
            ->assertSeeText('Pemantauan perawat untuk pasien dokter.')
            ->assertSeeText($nurse->email)
            ->assertSeeText('Diagnosis awal pasien.');

        $this->get(route('staff.doctor.inpatients.show', $otherAdmissionId))
            ->assertNotFound();

        $this->post(route('staff.doctor.inpatients.evaluations.store', $otherAdmissionId), [
            'update_status' => 'Stabil',
            'notes' => 'Tidak boleh mengubah pasien dokter lain.',
            'diagnosis' => 'Tidak boleh.',
            'treatment_plan' => 'Tidak boleh.',
            'discharge_decision' => 'Approve',
        ])->assertNotFound();

        $this->assertDatabaseMissing('inpatient_admissions', [
            'id' => $otherAdmissionId,
            'doctor_discharge_approved_by_user_id' => $doctor->user->id,
        ]);
    }

    public function test_doctor_can_record_follow_up_and_approve_inpatient_discharge(): void
    {
        [$doctor, $scheduleId] = $this->createDoctor('Dr. Utama');
        $admissionId = $this->createDoctorAdmission($doctor, $scheduleId, 'Pasien Evaluasi');

        $this->actingAs($doctor->user)
            ->post(route('staff.doctor.inpatients.evaluations.store', $admissionId), [
                'diagnosis' => 'Infeksi membaik, tanpa komplikasi.',
                'treatment_plan' => 'Lanjutkan terapi oral dan kontrol sesuai jadwal.',
                'discharge_decision' => 'Approve',
            ])
            ->assertRedirect(route('staff.doctor.inpatients.show', $admissionId))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('doctor_consultations', [
            'diagnosis' => 'Infeksi membaik, tanpa komplikasi.',
            'treatment_plan' => 'Lanjutkan terapi oral dan kontrol sesuai jadwal.',
        ]);
        $this->assertDatabaseCount('patient_care_updates', 0);
        $this->assertDatabaseHas('inpatient_admissions', [
            'id' => $admissionId,
            'doctor_discharge_approved_by_user_id' => $doctor->user->id,
        ]);
        self::assertNotNull(DB::table('inpatient_admissions')
            ->where('id', $admissionId)
            ->value('doctor_discharge_approved_at'));

        $receptionist = $this->createUser('Resepsionis');
        $this->actingAs($receptionist)
            ->get(route('staff.inpatient-admissions.index'))
            ->assertOk()
            ->assertSeeText($doctor->user->email)
            ->assertSeeText('Pasien Evaluasi');
    }

    public function test_doctor_can_continue_inpatient_care_and_revoke_pending_discharge_approval(): void
    {
        [$doctor, $scheduleId] = $this->createDoctor('Dr. Utama');
        $admissionId = $this->createDoctorAdmission($doctor, $scheduleId, 'Pasien Lanjut Rawat');
        DB::table('inpatient_admissions')->where('id', $admissionId)->update([
            'doctor_discharge_approved_by_user_id' => $doctor->user->id,
            'doctor_discharge_approved_at' => now(),
        ]);

        $this->actingAs($doctor->user)
            ->post(route('staff.doctor.inpatients.evaluations.store', $admissionId), [
                'diagnosis' => 'Infeksi masih perlu observasi.',
                'treatment_plan' => 'Lanjutkan terapi dan pemantauan.',
                'discharge_decision' => 'Continue',
            ])
            ->assertRedirect(route('staff.doctor.inpatients.show', $admissionId))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('inpatient_admissions', [
            'id' => $admissionId,
            'doctor_discharge_approved_by_user_id' => null,
            'doctor_discharge_approved_at' => null,
            'discharged_at' => null,
        ]);
    }

    public function test_any_doctor_can_refer_a_patient_to_a_specific_available_specialist(): void
    {
        [$doctor, $scheduleId] = $this->createDoctor('Dr. Neurologi', 'Neurologi', 'Spesialis Neurologi');
        [$receivingDoctor, $receivingScheduleId] = $this->createDoctor('Dr. Kardiologi', 'Kardiologi', 'Spesialis Kardiologi');
        $appointmentId = $this->createAppointment($scheduleId, 'Checked-In', 'Rani Pasien');
        $this->createNursingAssessment($appointmentId);
        $this->validatePatientCondition($doctor, $appointmentId);
        $referralSlot = $receivingScheduleId.'|'.Carbon::today()->toDateString();

        $this->actingAs($doctor->user)
            ->get(route('staff.doctor.appointments.show', $appointmentId))
            ->assertOk()
            ->assertSeeText('Rujukan ke dokter lain')
            ->assertSeeText('Kardiologi · Dr. Kardiologi');

        $this->post(route('staff.doctor.appointments.consultation.store', $appointmentId), [
            'diagnosis' => 'Perlu evaluasi jantung.',
            'treatment_plan' => 'Pemeriksaan lanjutan.',
            'disposition' => 'Rawat Jalan',
            'referral_slot' => $referralSlot,
            'referral_reason' => 'Nyeri dada berulang saat beraktivitas.',
            'examination_findings' => 'Nyeri tekan tidak ditemukan; EKG awal perlu evaluasi lanjutan.',
        ])
            ->assertRedirect(route('staff.doctor.appointments.show', $appointmentId))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('doctor_consultations', [
            'appointment_id' => $appointmentId,
            'referral_department_id' => DB::table('doctors')->where('id', $receivingDoctor->id)->value('department_id'),
            'referral_doctor_id' => $receivingDoctor->id,
            'referral_reason' => 'Nyeri dada berulang saat beraktivitas.',
            'examination_findings' => 'Nyeri tekan tidak ditemukan; EKG awal perlu evaluasi lanjutan.',
        ]);

        $referralConsultationId = DB::table('doctor_consultations')->where('appointment_id', $appointmentId)->value('id');
        $referralAppointment = DB::table('appointments')->where('referral_consultation_id', $referralConsultationId)->first();

        $this->assertNotNull($referralAppointment);
        $this->assertSame((int) $receivingScheduleId, (int) $referralAppointment->doctor_schedule_id);
        $this->assertSame('Pending', $referralAppointment->status);
        $this->assertSame(
            (int) $receivingDoctor->id,
            (int) DB::table('doctor_schedules')
                ->join('doctors', 'doctors.id', '=', 'doctor_schedules.doctor_id')
                ->where('doctor_schedules.id', $referralAppointment->doctor_schedule_id)
                ->value('doctors.id'),
        );

        $this->actingAs($receivingDoctor->user)
            ->get(route('staff.doctor.referrals.index'))
            ->assertOk()
            ->assertViewHas('appointments', fn ($appointments): bool => $appointments->count() === 1 && $appointments->total() === 1)
            ->assertSeeText('Rani Pasien')
            ->assertSeeText('Dr. Neurologi')
            ->assertSeeText('Nyeri dada berulang saat beraktivitas.')
            ->assertSeeText('EKG awal perlu evaluasi lanjutan.');

        $this->get(route('staff.doctor.appointments.show', $referralAppointment->id))
            ->assertOk()
            ->assertSeeText('Informasi rujukan')
            ->assertSeeText('Menunggu check-in pasien')
            ->assertSeeText('Nyeri dada berulang saat beraktivitas.')
            ->assertSeeText('Perlu evaluasi jantung.')
            ->assertSeeText('Kondisi pasien telah diperiksa dokter.');

        $this->actingAs($doctor->user)
            ->get(route('staff.doctor.referrals.index'))
            ->assertOk()
            ->assertDontSeeText('Rani Pasien');
    }

    public function test_doctor_cannot_select_a_slot_that_is_not_available_for_referral(): void
    {
        [$doctor, $scheduleId] = $this->createDoctor('Dr. Utama');
        [, $otherScheduleId] = $this->createDoctor('Dr. Umum Lain', 'Poli Umum Lain', 'Dokter Umum');
        $appointmentId = $this->createAppointment($scheduleId, 'Checked-In', 'Pasien Rujukan Tidak Valid');
        $this->createNursingAssessment($appointmentId);
        $this->validatePatientCondition($doctor, $appointmentId);

        $this->actingAs($doctor->user)
            ->post(route('staff.doctor.appointments.consultation.store', $appointmentId), [
                'diagnosis' => 'Diagnosis pasien.',
                'treatment_plan' => 'Rencana pemeriksaan.',
                'disposition' => 'Rawat Jalan',
                'referral_slot' => $otherScheduleId.'|'.Carbon::today()->toDateString(),
                'referral_reason' => 'Perlu penanganan dokter lain.',
                'examination_findings' => 'Hasil pemeriksaan.',
            ])
            ->assertSessionHasErrors('referral_slot');

        $this->assertDatabaseCount('doctor_consultations', 0);
    }

    public function test_doctor_must_add_referral_reason_and_findings_when_referring(): void
    {
        [$doctor, $scheduleId] = $this->createDoctor('Dr. Utama');
        [, $receivingScheduleId] = $this->createDoctor('Dr. Spesialis', 'Kardiologi', 'Spesialis Kardiologi');
        $appointmentId = $this->createAppointment($scheduleId, 'Checked-In', 'Pasien Rujukan');
        $this->createNursingAssessment($appointmentId);
        $this->validatePatientCondition($doctor, $appointmentId);

        $this->actingAs($doctor->user)
            ->post(route('staff.doctor.appointments.consultation.store', $appointmentId), [
                'diagnosis' => 'Diagnosis pasien.',
                'treatment_plan' => 'Rencana pemeriksaan.',
                'disposition' => 'Rawat Jalan',
                'referral_slot' => $receivingScheduleId.'|'.Carbon::today()->toDateString(),
            ])
            ->assertSessionHasErrors(['referral_reason', 'examination_findings']);

        $this->assertDatabaseCount('doctor_consultations', 0);
        $this->assertDatabaseCount('appointments', 1);
    }

    public function test_doctor_cannot_save_consultation_without_a_valid_disposition(): void
    {
        [$doctor, $scheduleId] = $this->createDoctor('Dr. Utama');
        $appointmentId = $this->createAppointment($scheduleId, 'Checked-In', 'Pasien Tanpa Keputusan');
        $this->createNursingAssessment($appointmentId);
        $this->validatePatientCondition($doctor, $appointmentId);

        $this->actingAs($doctor->user)
            ->post(route('staff.doctor.appointments.consultation.store', $appointmentId), [
                'diagnosis' => 'Diagnosis pasien.',
                'treatment_plan' => 'Rencana pemeriksaan.',
                'disposition' => 'Pulang saja',
                'referral_slot' => 'none',
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
            'referral_slot' => 'none',
        ])->assertNotFound();

        $this->assertDatabaseCount('doctor_consultations', 0);
    }

    public function test_non_doctor_cannot_open_doctor_workflow(): void
    {
        $receptionist = $this->createUser('Resepsionis');

        $this->actingAs($receptionist)
            ->get(route('staff.doctor.appointments.index'))
            ->assertForbidden();

        $this->get(route('staff.doctor.inpatients.index'))
            ->assertForbidden();
    }

    /**
     * @return array{object, int}
     */
    private function createDoctor(
        string $name,
        string $departmentName = 'Poli Umum',
        string $specialization = 'Dokter Umum',
    ): array {
        $user = $this->createUser('Dokter');
        $departmentId = DB::table('departments')->insertGetId(['name' => $departmentName]);
        $doctorId = DB::table('doctors')->insertGetId([
            'user_id' => $user->id,
            'department_id' => $departmentId,
            'full_name' => $name,
            'specialization' => $specialization,
        ]);
        $scheduleId = DB::table('doctor_schedules')->insertGetId([
            'doctor_id' => $doctorId,
            'day_of_week' => [
                1 => 'Senin',
                2 => 'Selasa',
                3 => 'Rabu',
                4 => 'Kamis',
                5 => 'Jumat',
                6 => 'Sabtu',
                7 => 'Minggu',
            ][Carbon::today()->dayOfWeekIso],
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

    private function createDoctorAdmission(object $doctor, int $scheduleId, string $patientName): int
    {
        $appointmentId = $this->createAppointment($scheduleId, 'Completed', $patientName);
        $patientId = (int) DB::table('appointments')->where('id', $appointmentId)->value('patient_id');

        DB::table('doctor_consultations')->insert([
            'appointment_id' => $appointmentId,
            'patient_id' => $patientId,
            'doctor_id' => $doctor->id,
            'diagnosis' => 'Diagnosis awal pasien.',
            'treatment_plan' => 'Instruksi pengobatan awal.',
            'disposition' => 'Rawat Inap',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('inpatient_admissions')->insertGetId([
            'patient_id' => $patientId,
            'appointment_id' => $appointmentId,
            'admission_reason' => 'Diagnosis awal pasien.',
            'admitted_by_user_id' => $doctor->user->id,
            'admitted_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createNursingAssessment(int $appointmentId): void
    {
        $patientId = DB::table('appointments')->where('id', $appointmentId)->value('patient_id');
        $nurse = $this->createUser('Perawat');

        DB::table('patient_care_updates')->insert([
            'appointment_id' => $appointmentId,
            'patient_id' => $patientId,
            'inpatient_admission_id' => null,
            'recorded_by_user_id' => $nurse->id,
            'update_status' => 'Menunggu Validasi Dokter',
            'temperature_celsius' => 36.8,
            'blood_pressure' => '120/80',
            'pulse_rate' => 76,
            'respiratory_rate' => 18,
            'oxygen_saturation' => 98,
            'notes' => 'Tanda vital dari pemeriksaan perawat.',
            'created_at' => now(),
        ]);
    }

    private function validatePatientCondition(object $doctor, int $appointmentId): void
    {
        $this->actingAs($doctor->user)
            ->post(route('staff.doctor.appointments.patient-condition.store', $appointmentId), [
                'update_status' => 'Stabil',
                'notes' => 'Kondisi pasien telah diperiksa dokter.',
            ])
            ->assertRedirect(route('staff.doctor.appointments.show', $appointmentId))
            ->assertSessionHas('success');
    }
}
