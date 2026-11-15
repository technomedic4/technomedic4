<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PharmacyWorkflowTest extends TestCase
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
            $table->string('password_hash');
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('patient_profiles', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('medical_record_number')->nullable();
            $table->string('full_name');
        });

        Schema::create('doctors', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('full_name');
        });

        Schema::create('doctor_consultations', function (Blueprint $table): void {
            $table->increments('id');
            $table->text('diagnosis');
        });

        Schema::create('bills', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedBigInteger('total_amount');
            $table->unsignedBigInteger('amount_paid')->default(0);
            $table->string('status');
        });

        Schema::create('prescriptions', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('consultation_id');
            $table->unsignedInteger('patient_id');
            $table->unsignedInteger('doctor_id');
            $table->unsignedInteger('bill_id');
            $table->unsignedInteger('dispensed_by_user_id')->nullable();
            $table->timestamp('dispensed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('prescription_items', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('prescription_id');
            $table->unsignedInteger('medicine_id');
            $table->string('medicine_name');
            $table->string('strength')->nullable();
            $table->string('dosage_form')->nullable();
            $table->string('unit');
            $table->unsignedInteger('quantity');
            $table->string('dosage');
            $table->text('instructions');
            $table->unsignedBigInteger('unit_price');
            $table->timestamps();
        });
    }

    public function test_pharmacist_cannot_dispense_before_payment_and_records_paid_dispensing(): void
    {
        $pharmacist = $this->createUser('Farmasi', 'pharmacy@example.com');
        $patientId = DB::table('patient_profiles')->insertGetId([
            'medical_record_number' => 'RM-00000001',
            'full_name' => 'Pasien Farmasi',
        ]);
        $doctorId = DB::table('doctors')->insertGetId(['full_name' => 'Dr. Contoh']);
        $consultationId = DB::table('doctor_consultations')->insertGetId([
            'diagnosis' => 'Demam',
        ]);
        $billId = DB::table('bills')->insertGetId([
            'total_amount' => 100000,
            'amount_paid' => 0,
            'status' => 'Unpaid',
        ]);
        $prescriptionId = DB::table('prescriptions')->insertGetId([
            'consultation_id' => $consultationId,
            'patient_id' => $patientId,
            'doctor_id' => $doctorId,
            'bill_id' => $billId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('prescription_items')->insert([
            'prescription_id' => $prescriptionId,
            'medicine_id' => 1,
            'medicine_name' => 'Parasetamol',
            'strength' => '500 mg',
            'dosage_form' => 'Tablet',
            'unit' => 'tablet',
            'quantity' => 4,
            'dosage' => '1 tablet',
            'instructions' => '3 kali sehari sesudah makan',
            'unit_price' => 25000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($pharmacist)
            ->get(route('staff.pharmacy.index'))
            ->assertOk()
            ->assertSeeText('Pasien Farmasi')
            ->assertSeeText('Parasetamol')
            ->assertSeeText('Menunggu pembayaran')
            ->assertDontSeeText('25000')
            ->assertDontSeeText('100000');

        $this->post(route('staff.pharmacy.prescriptions.dispense', $prescriptionId))
            ->assertSessionHasErrors([
                'prescription' => 'Obat hanya dapat diserahkan setelah keuangan mengonfirmasi tagihan lunas.',
            ]);
        $this->assertDatabaseHas('prescriptions', [
            'id' => $prescriptionId,
            'dispensed_at' => null,
            'dispensed_by_user_id' => null,
        ]);

        DB::table('bills')->where('id', $billId)->update([
            'amount_paid' => 100000,
            'status' => 'Paid',
        ]);

        $this->get(route('staff.pharmacy.index'))
            ->assertOk()
            ->assertSeeText('Siap diserahkan')
            ->assertSeeText('Konfirmasi obat diserahkan');

        $this->post(route('staff.pharmacy.prescriptions.dispense', $prescriptionId))
            ->assertRedirect(route('staff.pharmacy.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('prescriptions', [
            'id' => $prescriptionId,
            'dispensed_by_user_id' => $pharmacist->id,
        ]);
        self::assertNotNull(DB::table('prescriptions')->where('id', $prescriptionId)->value('dispensed_at'));

        $this->post(route('staff.pharmacy.prescriptions.dispense', $prescriptionId))
            ->assertSessionHasErrors('prescription');
    }

    public function test_non_pharmacy_staff_cannot_access_the_pharmacy_queue(): void
    {
        $finance = $this->createUser('Keuangan', 'finance@example.com');

        $this->actingAs($finance)
            ->get(route('staff.pharmacy.index'))
            ->assertForbidden();
    }

    public function test_pharmacy_staff_is_redirected_from_the_dashboard_to_the_prescription_queue(): void
    {
        $pharmacist = $this->createUser('Farmasi', 'pharmacy@example.com');

        $this->actingAs($pharmacist)
            ->get(route('staff.dashboard'))
            ->assertRedirect(route('staff.pharmacy.index'));
    }

    private function createUser(string $roleName, string $email): User
    {
        $roleId = DB::table('roles')->insertGetId(['role_name' => $roleName]);
        $userId = DB::table('users')->insertGetId([
            'role_id' => $roleId,
            'email' => $email,
            'password_hash' => Hash::make('password123'),
            'is_active' => true,
            'created_at' => now(),
        ]);

        return User::query()->findOrFail($userId);
    }
}
