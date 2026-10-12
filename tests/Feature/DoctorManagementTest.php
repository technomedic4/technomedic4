<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DoctorManagementTest extends TestCase
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
            $table->string('phone_number')->unique();
            $table->string('password_hash');
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('departments', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('name');
        });

        Schema::create('doctors', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('user_id')->unique();
            $table->unsignedInteger('department_id');
            $table->string('full_name', 150);
            $table->string('specialization', 100);
            $table->string('str_number', 100)->unique();
        });

        DB::table('roles')->insert(['role_name' => 'Dokter']);
    }

    public function test_receptionist_can_create_a_doctor_account_and_link_the_doctor_profile(): void
    {
        $departmentId = DB::table('departments')->insertGetId(['name' => 'Poli Anak']);

        $this->actingAs($this->createUser('Resepsionis'))
            ->post(route('staff.management.doctors.store'), [
                'full_name' => 'Dr. Sari Utami',
                'email' => 'sari.utami@example.com',
                'phone_number' => '081234567890',
                'password' => 'safe-doctor-password',
                'password_confirmation' => 'safe-doctor-password',
                'department_id' => $departmentId,
                'specialization' => 'Dokter Anak',
                'str_number' => 'STR-ANAK-001',
            ])
            ->assertRedirect(route('staff.management.doctors.index'))
            ->assertSessionHas('success');

        $doctorAccount = DB::table('users')
            ->join('roles', 'roles.id', '=', 'users.role_id')
            ->where('users.email', 'sari.utami@example.com')
            ->select('users.*', 'roles.role_name')
            ->first();

        $this->assertNotNull($doctorAccount);
        $this->assertSame('Dokter', $doctorAccount->role_name);
        $this->assertTrue((bool) $doctorAccount->is_active);
        $this->assertTrue(Hash::check('safe-doctor-password', $doctorAccount->password_hash));
        $this->assertDatabaseHas('doctors', [
            'user_id' => $doctorAccount->id,
            'department_id' => $departmentId,
            'full_name' => 'Dr. Sari Utami',
            'specialization' => 'Dokter Anak',
            'str_number' => 'STR-ANAK-001',
        ]);

        $this->get(route('staff.management.doctors.index'))
            ->assertOk()
            ->assertSeeText('Dr. Sari Utami')
            ->assertSeeText('Poli Anak')
            ->assertSeeText('sari.utami@example.com');

        $this->post(route('login.attempt'), [
            'email' => 'sari.utami@example.com',
            'password' => 'safe-doctor-password',
        ])->assertRedirect(route('staff.dashboard'));
    }

    public function test_admin_and_other_staff_cannot_manage_doctor_accounts(): void
    {
        $this->actingAs($this->createUser('Admin'))
            ->get(route('staff.management.doctors.index'))
            ->assertForbidden();

        $this->actingAs($this->createUser('Resepsionis'))
            ->post(route('staff.management.doctors.store'), [])
            ->assertSessionHasErrors([
                'full_name',
                'email',
                'phone_number',
                'password',
                'department_id',
                'specialization',
                'str_number',
            ]);

        $this->actingAs($this->createUser('Dokter'))
            ->get(route('staff.management.doctors.index'))
            ->assertForbidden();
    }

    public function test_doctor_account_creation_validates_password_confirmation_and_unique_identifiers(): void
    {
        $receptionist = $this->createUser('Resepsionis');
        $existingDoctor = $this->createUser('Dokter');
        $departmentId = DB::table('departments')->insertGetId(['name' => 'Poli Umum']);
        DB::table('doctors')->insert([
            'user_id' => $existingDoctor->id,
            'department_id' => $departmentId,
            'full_name' => 'Dr. Existing',
            'specialization' => 'Umum',
            'str_number' => 'STR-EXISTING',
        ]);

        $this->actingAs($receptionist)
            ->from(route('staff.management.doctors.index'))
            ->post(route('staff.management.doctors.store'), [
                'full_name' => 'Dr. Baru',
                'email' => $existingDoctor->email,
                'phone_number' => $existingDoctor->phone_number,
                'password' => 'safe-doctor-password',
                'password_confirmation' => 'different-password',
                'department_id' => $departmentId,
                'specialization' => 'Dokter Umum',
                'str_number' => 'STR-EXISTING',
            ])
            ->assertRedirect(route('staff.management.doctors.index'))
            ->assertSessionHasErrors(['email', 'phone_number', 'password', 'str_number']);

        $this->assertDatabaseCount('doctors', 1);
    }

    private function createUser(string $roleName): User
    {
        $roleId = DB::table('roles')->where('role_name', $roleName)->value('id')
            ?? DB::table('roles')->insertGetId(['role_name' => $roleName]);
        $userId = DB::table('users')->insertGetId([
            'role_id' => $roleId,
            'email' => strtolower($roleName).'-'.$roleId.'@example.com',
            'phone_number' => '0800000000'.str_pad((string) $roleId, 2, '0', STR_PAD_LEFT),
            'password_hash' => Hash::make('password123'),
            'is_active' => true,
        ]);

        return User::query()->findOrFail($userId);
    }
}
