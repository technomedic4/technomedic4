<?php

namespace Tests\Feature\Console\Commands;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ProvisionWorkflowStaffTest extends TestCase
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
    }

    public function test_provisions_active_finance_and_pharmacy_accounts_with_random_passwords(): void
    {
        $status = Artisan::call('app:provision-workflow-staff', [
            '--finance-email' => 'finance@test.local',
            '--pharmacy-email' => 'pharmacy@test.local',
        ]);
        $output = Artisan::output();

        self::assertSame(0, $status);
        self::assertMatchesRegularExpression('/Keuangan: finance@test\.local/', $output);
        self::assertMatchesRegularExpression('/Farmasi: pharmacy@test\.local/', $output);

        preg_match_all('/Kata sandi sementara \(ditampilkan sekali\): ([A-Za-z0-9]+)/', $output, $matches);
        self::assertCount(2, $matches[1]);

        foreach ([
            ['Keuangan', 'finance@test.local', $matches[1][0]],
            ['Farmasi', 'pharmacy@test.local', $matches[1][1]],
        ] as [$roleName, $email, $password]) {
            $user = DB::table('users')
                ->join('roles', 'roles.id', '=', 'users.role_id')
                ->where('users.email', $email)
                ->select(['users.password_hash', 'users.is_active', 'roles.role_name'])
                ->first();

            self::assertSame($roleName, $user->role_name);
            self::assertSame(1, (int) $user->is_active);
            self::assertTrue(Hash::check($password, $user->password_hash));
        }
    }

    public function test_does_not_create_either_account_if_an_email_is_already_in_use(): void
    {
        $financeRoleId = DB::table('roles')->insertGetId(['role_name' => 'Keuangan']);
        DB::table('users')->insert([
            'role_id' => $financeRoleId,
            'email' => 'finance@test.local',
            'password_hash' => Hash::make('existing-password'),
            'is_active' => true,
        ]);

        $status = Artisan::call('app:provision-workflow-staff', [
            '--finance-email' => 'finance@test.local',
            '--pharmacy-email' => 'pharmacy@test.local',
        ]);

        self::assertSame(1, $status);
        $this->assertDatabaseMissing('users', ['email' => 'pharmacy@test.local']);
    }

    public function test_reset_existing_replaces_passwords_for_workflow_accounts(): void
    {
        $financeRoleId = DB::table('roles')->insertGetId(['role_name' => 'Keuangan']);
        DB::table('users')->insert([
            'role_id' => $financeRoleId,
            'email' => 'finance@test.local',
            'phone_number' => null,
            'password_hash' => Hash::make('old-finance-password'),
            'is_active' => true,
        ]);

        $status = Artisan::call('app:provision-workflow-staff', [
            '--finance-email' => 'finance@test.local',
            '--pharmacy-email' => 'pharmacy@test.local',
            '--reset-existing' => true,
        ]);
        $output = Artisan::output();

        self::assertSame(0, $status);
        preg_match_all('/Kata sandi sementara \(ditampilkan sekali\): ([A-Za-z0-9]+)/', $output, $matches);
        self::assertCount(2, $matches[1]);
        self::assertDatabaseCount('users', 2);

        $finance = DB::table('users')->where('email', 'finance@test.local')->first();
        self::assertTrue(Hash::check($matches[1][0], $finance->password_hash));
        self::assertFalse(Hash::check('old-finance-password', $finance->password_hash));

        $this->post(route('login.attempt'), [
            'email' => 'finance@test.local',
            'password' => $matches[1][0],
        ])
            ->assertRedirect(route('staff.dashboard'))
            ->assertSessionHasNoErrors();
    }
}
