<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BillingManagementTest extends TestCase
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
        });

        Schema::create('family_relationships', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('patient_id');
            $table->unsignedInteger('family_user_id');
            $table->string('relationship_type');
            $table->string('access_permission');
            $table->string('status');
            $table->timestamp('created_at')->nullable();
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

        Schema::create('inpatient_admissions', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('patient_id');
            $table->string('room_number')->nullable();
            $table->string('bed_number')->nullable();
            $table->text('admission_reason');
            $table->unsignedInteger('admitted_by_user_id');
            $table->timestamp('admitted_at');
            $table->timestamp('discharged_at')->nullable();
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

        Schema::create('bill_payments', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('bill_id');
            $table->unsignedInteger('recorded_by_user_id');
            $table->unsignedBigInteger('amount');
            $table->string('payment_method');
            $table->string('reference')->nullable();
            $table->timestamp('paid_at');
            $table->timestamps();
        });

        Schema::create('mobile_payment_submissions', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('bill_id');
            $table->unsignedInteger('submitted_by_user_id');
            $table->unsignedInteger('reviewed_by_user_id')->nullable();
            $table->unsignedBigInteger('amount');
            $table->string('payment_method', 20);
            $table->string('reference', 100)->nullable();
            $table->string('proof_path', 500);
            $table->string('status', 20)->default('Pending');
            $table->text('review_note')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
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

    public function test_finance_staff_can_create_edit_view_and_delete_an_unpaid_bill(): void
    {
        $finance = $this->createUser('Keuangan', 'finance@example.com');
        $patientId = $this->createPatient(null, 'Maya Pasien');

        $this->actingAs($finance)
            ->get(route('staff.billing.create'))
            ->assertOk()
            ->assertSeeText('Buat tagihan');

        $this->post(route('staff.billing.store'), [
            'patient_id' => $patientId,
            'items' => [
                ['item_name' => 'Pemeriksaan dokter', 'quantity' => 2, 'unit_price' => 50000],
                ['item_name' => "<script>alert('xss')</script>", 'quantity' => 1, 'unit_price' => 1000],
            ],
        ])
            ->assertRedirect();

        $bill = DB::table('bills')->first();
        self::assertSame(101000, (int) $bill->total_amount);
        self::assertSame('Unpaid', $bill->status);
        $this->assertDatabaseHas('bill_items', [
            'bill_id' => $bill->id,
            'item_name' => 'Pemeriksaan dokter',
            'quantity' => 2,
            'unit_price' => 50000,
        ]);

        $detailResponse = $this->get(route('staff.billing.show', $bill->id))->assertOk();
        self::assertStringContainsString('&lt;script&gt;', $detailResponse->getContent());
        self::assertStringNotContainsString("<script>alert('xss')</script>", $detailResponse->getContent());

        $this->get(route('staff.billing.edit', $bill->id))
            ->assertOk()
            ->assertSeeText('Edit tagihan');

        $this->put(route('staff.billing.update', $bill->id), [
            'patient_id' => $patientId,
            'items' => [
                ['item_name' => 'Konsultasi', 'quantity' => 1, 'unit_price' => 75000],
            ],
        ])
            ->assertRedirect(route('staff.billing.show', $bill->id))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('bills', [
            'id' => $bill->id,
            'total_amount' => 75000,
        ]);
        $this->assertDatabaseHas('bill_items', [
            'bill_id' => $bill->id,
            'item_name' => 'Konsultasi',
            'unit_price' => 75000,
        ]);
        $this->assertDatabaseMissing('bill_items', [
            'bill_id' => $bill->id,
            'item_name' => 'Pemeriksaan dokter',
        ]);

        $this->delete(route('staff.billing.destroy', $bill->id))
            ->assertRedirect(route('staff.billing.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('bills', ['id' => $bill->id]);
    }

    public function test_receptionist_can_add_a_nurse_recorded_treatment_to_a_bill_with_their_own_price(): void
    {
        $receptionist = $this->createUser('Resepsionis', 'reception@example.com');
        $nurse = $this->createUser('Perawat', 'nurse@example.com');
        $patientId = $this->createPatient(null, 'Pasien Rawat Inap');
        $admissionId = DB::table('inpatient_admissions')->insertGetId([
            'patient_id' => $patientId,
            'admission_reason' => 'Observasi.',
            'admitted_by_user_id' => $receptionist->id,
            'admitted_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $treatmentId = DB::table('inpatient_nursing_treatments')->insertGetId([
            'admission_id' => $admissionId,
            'patient_id' => $patientId,
            'treatment_name' => 'Perawatan luka',
            'notes' => 'Dicatat oleh perawat.',
            'recorded_by_user_id' => $nurse->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($receptionist)
            ->get(route('staff.billing.create', ['patient_id' => $patientId]))
            ->assertOk()
            ->assertSeeText('Bukti tindakan dari perawat')
            ->assertSeeText('Perawatan luka')
            ->assertSeeText('Nominal diisi petugas billing yang berwenang, bukan perawat')
            ->assertSee('value="'.$patientId.'" selected', false);

        $this->post(route('staff.billing.store'), [
            'patient_id' => $patientId,
            'items' => [[
                'item_name' => 'Nama tindakan yang dimanipulasi',
                'quantity' => 1,
                'unit_price' => 65000,
                'nursing_treatment_id' => $treatmentId,
            ]],
        ])->assertRedirect();

        $bill = DB::table('bills')->first();
        $billItem = DB::table('bill_items')->where('bill_id', $bill->id)->first();
        self::assertSame(65000, (int) $bill->total_amount);
        self::assertSame('Perawatan luka', $billItem->item_name);
        $this->assertDatabaseHas('inpatient_nursing_treatments', [
            'id' => $treatmentId,
            'bill_item_id' => $billItem->id,
        ]);
        $this->get(route('staff.billing.show', $bill->id))
            ->assertOk()
            ->assertSeeText('Catatan tindakan perawat')
            ->assertSeeText('Dicatat oleh perawat.');

        $this->put(route('staff.billing.update', $bill->id), [
            'patient_id' => $patientId,
            'items' => [[
                'item_name' => 'Nama tindakan yang dimanipulasi',
                'quantity' => 1,
                'unit_price' => 70000,
                'nursing_treatment_id' => $treatmentId,
            ]],
        ])->assertRedirect(route('staff.billing.show', $bill->id));

        $updatedBillItem = DB::table('bill_items')->where('bill_id', $bill->id)->first();
        self::assertSame('Perawatan luka', $updatedBillItem->item_name);
        $this->assertDatabaseHas('inpatient_nursing_treatments', [
            'id' => $treatmentId,
            'bill_item_id' => $updatedBillItem->id,
        ]);

        $this->post(route('staff.billing.store'), [
            'patient_id' => $patientId,
            'items' => [[
                'item_name' => 'Perawatan luka',
                'quantity' => 1,
                'unit_price' => 65000,
                'nursing_treatment_id' => $treatmentId,
            ]],
        ])->assertSessionHasErrors('items');

        $this->assertDatabaseCount('bills', 1);
    }

    public function test_qris_is_available_and_can_be_recorded_as_a_payment_method(): void
    {
        $finance = $this->createUser('Keuangan', 'finance@example.com');
        $patientId = $this->createPatient(null, 'Rina Pasien');
        $billId = $this->createBill($patientId, $finance->id, 100000);

        $this->actingAs($finance)
            ->get(route('staff.billing.show', $billId))
            ->assertOk()
            ->assertSee('value="QRIS"', false)
            ->assertSeeText('QRIS');

        $this->post(route('staff.billing.payments.store', $billId), [
            'amount' => 40000,
            'payment_method' => 'QRIS',
            'reference' => 'QRIS-001',
        ])
            ->assertRedirect(route('staff.billing.show', $billId))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('bills', [
            'id' => $billId,
            'amount_paid' => 40000,
            'status' => 'Partially Paid',
        ]);
        $this->assertDatabaseHas('bill_payments', [
            'bill_id' => $billId,
            'amount' => 40000,
            'payment_method' => 'QRIS',
            'reference' => 'QRIS-001',
            'recorded_by_user_id' => $finance->id,
        ]);

        $this->assertDatabaseCount('bill_payments', 1);
    }

    public function test_finance_staff_can_record_partial_payment_and_full_payment(): void
    {
        $finance = $this->createUser('Keuangan', 'finance@example.com');
        $patientId = $this->createPatient(null, 'Rina Pasien');
        $billId = $this->createBill($patientId, $finance->id, 100000);

        $this->actingAs($finance)
            ->post(route('staff.billing.payments.store', $billId), [
                'amount' => 40000,
                'payment_method' => 'Transfer',
                'reference' => 'TRX-001',
            ])
            ->assertRedirect(route('staff.billing.show', $billId))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('bills', [
            'id' => $billId,
            'amount_paid' => 40000,
            'status' => 'Partially Paid',
        ]);
        $this->assertDatabaseHas('bill_payments', [
            'bill_id' => $billId,
            'amount' => 40000,
            'payment_method' => 'Transfer',
            'reference' => 'TRX-001',
            'recorded_by_user_id' => $finance->id,
        ]);

        $this->post(route('staff.billing.payments.store', $billId), [
            'amount' => 60000,
            'payment_method' => 'Cash',
        ])
            ->assertRedirect(route('staff.billing.show', $billId));

        $this->assertDatabaseHas('bills', [
            'id' => $billId,
            'amount_paid' => 100000,
            'status' => 'Paid',
        ]);
        $this->assertDatabaseCount('bill_payments', 2);
    }

    public function test_payment_cannot_exceed_the_remaining_bill_balance(): void
    {
        $finance = $this->createUser('Keuangan', 'finance@example.com');
        $patientId = $this->createPatient(null, 'Rina Pasien');
        $billId = $this->createBill($patientId, $finance->id, 100000);

        $this->actingAs($finance)
            ->post(route('staff.billing.payments.store', $billId), [
                'amount' => 100001,
                'payment_method' => 'Cash',
            ])
            ->assertSessionHasErrors(['amount' => 'Jumlah pembayaran melebihi sisa tagihan.']);

        $this->assertDatabaseHas('bills', [
            'id' => $billId,
            'amount_paid' => 0,
            'status' => 'Unpaid',
        ]);
        $this->assertDatabaseCount('bill_payments', 0);
    }

    public function test_bill_with_a_payment_cannot_be_deleted_or_reduced_below_the_paid_amount(): void
    {
        $finance = $this->createUser('Keuangan', 'finance@example.com');
        $patientId = $this->createPatient(null, 'Maya Pasien');
        $billId = $this->createBill($patientId, $finance->id, 100000);
        DB::table('bill_payments')->insert([
            'bill_id' => $billId,
            'recorded_by_user_id' => $finance->id,
            'amount' => 50000,
            'payment_method' => 'Cash',
            'paid_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('bills')->where('id', $billId)->update([
            'amount_paid' => 50000,
            'status' => 'Partially Paid',
        ]);

        $this->actingAs($finance)
            ->delete(route('staff.billing.destroy', $billId))
            ->assertSessionHasErrors(['bill' => 'Tagihan yang sudah memiliki pembayaran tidak dapat dihapus.']);

        $this->put(route('staff.billing.update', $billId), [
            'patient_id' => $patientId,
            'items' => [
                ['item_name' => 'Biaya konsultasi', 'quantity' => 1, 'unit_price' => 20000],
            ],
        ])
            ->assertSessionHasErrors(['items' => 'Total tagihan tidak boleh lebih kecil dari pembayaran yang sudah diterima.']);

        $otherPatientId = $this->createPatient(null, 'Pasien Lain');
        $this->put(route('staff.billing.update', $billId), [
            'patient_id' => $otherPatientId,
            'items' => [
                ['item_name' => 'Biaya konsultasi', 'quantity' => 1, 'unit_price' => 60000],
            ],
        ])
            ->assertSessionHasErrors(['patient_id' => 'Pasien tidak dapat diubah setelah pembayaran tercatat.']);

        $this->assertDatabaseHas('bills', [
            'id' => $billId,
            'patient_id' => $patientId,
            'total_amount' => 100000,
            'amount_paid' => 50000,
            'status' => 'Partially Paid',
        ]);
    }

    public function test_unpaid_bill_can_be_cancelled_but_cannot_receive_a_payment_afterward(): void
    {
        $finance = $this->createUser('Keuangan', 'finance@example.com');
        $patientId = $this->createPatient(null, 'Maya Pasien');
        $billId = $this->createBill($patientId, $finance->id, 100000);

        $this->actingAs($finance)
            ->post(route('staff.billing.cancel', $billId))
            ->assertRedirect(route('staff.billing.show', $billId))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('bills', [
            'id' => $billId,
            'status' => 'Cancelled',
            'amount_paid' => 0,
        ]);

        $this->post(route('staff.billing.payments.store', $billId), [
            'amount' => 10000,
            'payment_method' => 'Cash',
        ])
            ->assertSessionHasErrors(['amount' => 'Pembayaran tidak dapat dicatat untuk tagihan yang dibatalkan.']);

        $this->assertDatabaseCount('bill_payments', 0);
    }

    public function test_receptionist_can_access_billing_and_create_bill_but_other_roles_cannot(): void
    {
        $receptionist = $this->createUser('Resepsionis', 'reception@example.com');
        $patientId = $this->createPatient(null, 'Pasien Resepsionis');

        $this->get(route('staff.billing.index'))
            ->assertRedirect(route('login'));

        $this->actingAs($receptionist)
            ->get(route('staff.billing.index'))
            ->assertOk()
            ->assertSee(route('staff.billing.create'))
            ->assertSeeText('Buat tagihan');

        $this->get(route('staff.billing.create'))
            ->assertOk()
            ->assertSeeText('Buat tagihan');

        $this->post(route('staff.billing.store'), [
            'patient_id' => $patientId,
            'items' => [
                ['item_name' => 'Pendaftaran', 'quantity' => 1, 'unit_price' => 50000],
            ],
        ])
            ->assertRedirect();

        $this->assertDatabaseHas('bills', [
            'patient_id' => $patientId,
            'total_amount' => 50000,
            'created_by_user_id' => $receptionist->id,
        ]);

        $this->get(route('staff.dashboard'))
            ->assertOk()
            ->assertSee(route('staff.billing.index'))
            ->assertSee(route('staff.billing.create'))
            ->assertSeeText('Buat tagihan');

        $doctor = $this->createUser('Dokter', 'doctor@example.com');

        $this->actingAs($doctor)
            ->get(route('staff.billing.index'))
            ->assertForbidden();

        $nurse = $this->createUser('Perawat', 'nurse@example.com');
        $billId = (int) DB::table('bills')->value('id');

        $this->actingAs($nurse)
            ->get(route('staff.billing.index'))
            ->assertForbidden();
        $this->get(route('staff.billing.create'))
            ->assertForbidden();
        $this->post(route('staff.billing.store'), [
            'patient_id' => $patientId,
            'items' => [['item_name' => 'Layanan', 'quantity' => 1, 'unit_price' => 1000]],
        ])->assertForbidden();
        $this->post(route('staff.billing.payments.store', $billId), [
            'amount' => 1000,
            'payment_method' => 'Cash',
        ])->assertForbidden();

        $admin = $this->createUser('Admin', 'admin@example.com');

        $this->actingAs($admin)
            ->get(route('staff.billing.index'))
            ->assertOk();
    }

    public function test_patient_can_read_only_their_own_bill_through_the_mobile_api(): void
    {
        $patient = $this->createUser('Pasien', 'patient@example.com');
        $patientId = $this->createPatient($patient->id, 'Maya Pasien');
        $billId = $this->createBill($patientId, $patient->id, 75000);

        $response = $this->withToken($this->issueToken($patient))
            ->getJson('/api/v1/bills?patient_id='.$patientId)
            ->assertOk()
            ->assertJsonPath('data.data.0.patient_name', 'Maya Pasien')
            ->assertJsonPath('data.data.0.status', 'Unpaid')
            ->assertJsonPath('data.data.0.total_amount', 75000)
            ->assertJsonPath('data.data.0.balance_due', 75000)
            ->assertJsonPath('data.data.0.items.0.item_name', 'Biaya layanan');

        self::assertSame($billId, $response->json('data.data.0.id'));
    }

    public function test_family_can_read_bills_only_for_approved_linked_patients(): void
    {
        $family = $this->createUser('Keluarga', 'family@example.com');
        $linkedPatientId = $this->createPatient(null, 'Pasien Terhubung');
        $unrelatedPatientId = $this->createPatient(null, 'Pasien Lain');
        DB::table('family_relationships')->insert([
            'patient_id' => $linkedPatientId,
            'family_user_id' => $family->id,
            'relationship_type' => 'Anak',
            'access_permission' => 'Read-Only',
            'status' => 'Approved',
            'created_at' => now(),
        ]);
        $this->createBill($linkedPatientId, $family->id, 50000);
        $this->createBill($unrelatedPatientId, $family->id, 25000);

        $token = $this->issueToken($family);
        $this->withToken($token)
            ->getJson('/api/v1/bills')
            ->assertOk()
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.patient_name', 'Pasien Terhubung');

        Auth::forgetGuards();

        $this->withToken($token)
            ->getJson('/api/v1/bills?patient_id='.$unrelatedPatientId)
            ->assertNotFound();
    }

    public function test_patient_payment_proof_requires_staff_approval_before_bill_is_paid(): void
    {
        Storage::fake('local');

        $patient = $this->createUser('Pasien', 'patient@example.com');
        $finance = $this->createUser('Keuangan', 'finance@example.com');
        $patientId = $this->createPatient($patient->id, 'Maya Pasien');
        $billId = $this->createBill($patientId, $finance->id, 75000);

        $response = $this->withToken($this->issueToken($patient))
            ->post("/api/v1/bills/{$billId}/payment-submissions", [
                'payment_method' => 'Transfer',
                'reference' => 'TRX-123',
                'proof' => UploadedFile::fake()->image('proof.jpg'),
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'Pending');
        $submissionId = $response->json('data.id');

        $this->assertDatabaseHas('mobile_payment_submissions', [
            'id' => $submissionId,
            'bill_id' => $billId,
            'submitted_by_user_id' => $patient->id,
            'amount' => 75000,
            'status' => 'Pending',
        ]);
        $this->assertDatabaseHas('bills', [
            'id' => $billId,
            'amount_paid' => 0,
            'status' => 'Unpaid',
        ]);
        $this->assertDatabaseCount('bill_payments', 0);

        $this->actingAs($finance)
            ->post(route('staff.billing.payment-submissions.review', [$billId, $submissionId]), [
                'decision' => 'Approved',
                'review_note' => 'Pembayaran diterima.',
            ])
            ->assertRedirect(route('staff.billing.show', $billId))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('mobile_payment_submissions', [
            'id' => $submissionId,
            'status' => 'Approved',
            'reviewed_by_user_id' => $finance->id,
        ]);
        $this->assertDatabaseHas('bill_payments', [
            'bill_id' => $billId,
            'recorded_by_user_id' => $finance->id,
            'amount' => 75000,
            'payment_method' => 'Transfer',
        ]);
        $this->assertDatabaseHas('bills', [
            'id' => $billId,
            'amount_paid' => 75000,
            'status' => 'Paid',
        ]);
    }

    public function test_rejected_mobile_payment_does_not_change_bill_balance(): void
    {
        Storage::fake('local');

        $patient = $this->createUser('Pasien', 'patient@example.com');
        $finance = $this->createUser('Keuangan', 'finance@example.com');
        $patientId = $this->createPatient($patient->id, 'Maya Pasien');
        $billId = $this->createBill($patientId, $finance->id, 75000);

        $response = $this->withToken($this->issueToken($patient))
            ->post("/api/v1/bills/{$billId}/payment-submissions", [
                'payment_method' => 'QRIS',
                'proof' => UploadedFile::fake()->image('proof.jpg'),
            ])
            ->assertCreated();

        $this->actingAs($finance)
            ->post(route('staff.billing.payment-submissions.review', [
                $billId,
                $response->json('data.id'),
            ]), [
                'decision' => 'Rejected',
                'review_note' => 'Bukti tidak valid.',
            ])
            ->assertRedirect(route('staff.billing.show', $billId));

        $this->assertDatabaseHas('mobile_payment_submissions', [
            'id' => $response->json('data.id'),
            'status' => 'Rejected',
        ]);
        $this->assertDatabaseHas('bills', [
            'id' => $billId,
            'amount_paid' => 0,
            'status' => 'Unpaid',
        ]);
        $this->assertDatabaseCount('bill_payments', 0);
    }

    private function createUser(string $roleName, string $email): User
    {
        $roleId = DB::table('roles')->where('role_name', $roleName)->value('id')
            ?? DB::table('roles')->insertGetId(['role_name' => $roleName]);

        $userId = DB::table('users')->insertGetId([
            'role_id' => $roleId,
            'email' => $email,
            'phone_number' => '081234567890',
            'password_hash' => Hash::make('password123'),
            'is_active' => true,
            'created_at' => now(),
        ]);

        return User::query()->findOrFail($userId);
    }

    private function createPatient(?int $userId, string $name): int
    {
        return (int) DB::table('patient_profiles')->insertGetId([
            'user_id' => $userId,
            'medical_record_number' => 'RM-'.str_pad((string) (DB::table('patient_profiles')->count() + 1), 8, '0', STR_PAD_LEFT),
            'full_name' => $name,
        ]);
    }

    private function createBill(int $patientId, int $createdByUserId, int $amount): int
    {
        $now = now();
        $billId = (int) DB::table('bills')->insertGetId([
            'bill_number' => 'INV-TEST-'.str_pad((string) (DB::table('bills')->count() + 1), 3, '0', STR_PAD_LEFT),
            'patient_id' => $patientId,
            'total_amount' => $amount,
            'amount_paid' => 0,
            'status' => 'Unpaid',
            'created_by_user_id' => $createdByUserId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('bill_items')->insert([
            'bill_id' => $billId,
            'item_name' => 'Biaya layanan',
            'quantity' => 1,
            'unit_price' => $amount,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $billId;
    }

    private function issueToken(User $user): string
    {
        return $user->createToken('billing-test', ['mobile:read'])->plainTextToken;
    }
}
