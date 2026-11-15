<?php

namespace App\Console\Commands;

use App\Support\HospitalStaffRole;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

#[Signature('app:provision-workflow-staff {--finance-email=keuangan@technomedic.local : Email akun bagian keuangan} {--pharmacy-email=farmasi@technomedic.local : Email akun bagian farmasi} {--reset-existing : Replace passwords on matching existing workflow accounts}')]
#[Description('Create or reset finance and pharmacy accounts with one-time random passwords')]
class ProvisionWorkflowStaff extends Command
{
    public function handle(): int
    {
        $accounts = [
            HospitalStaffRole::Finance->value => strtolower(trim((string) $this->option('finance-email'))),
            HospitalStaffRole::Pharmacy->value => strtolower(trim((string) $this->option('pharmacy-email'))),
        ];

        if (
            count(array_unique($accounts)) !== count($accounts)
            || collect($accounts)->contains(fn (string $email): bool => filter_var($email, FILTER_VALIDATE_EMAIL) === false)
        ) {
            $this->error('Alamat email akun keuangan dan farmasi harus valid dan berbeda.');

            return self::FAILURE;
        }

        $existingAccounts = DB::table('users')
            ->join('roles', 'roles.id', '=', 'users.role_id')
            ->whereIn('email', array_values($accounts))
            ->get(['users.email', 'roles.role_name'])
            ->keyBy('email');

        if ($existingAccounts->isNotEmpty() && ! $this->option('reset-existing')) {
            $this->error('Email sudah terdaftar. Gunakan --reset-existing hanya jika Anda memang ingin mengganti kata sandi akun bagian terkait.');

            return self::FAILURE;
        }

        foreach ($accounts as $roleName => $email) {
            $existingAccount = $existingAccounts->get($email);

            if ($existingAccount !== null && $existingAccount->role_name !== $roleName) {
                $this->error("Email {$email} sudah terhubung ke role {$existingAccount->role_name}; akun tidak diubah.");

                return self::FAILURE;
            }
        }

        $credentials = DB::transaction(function () use ($accounts, $existingAccounts): array {
            $credentials = [];

            foreach ($accounts as $roleName => $email) {
                $roleId = DB::table('roles')->where('role_name', $roleName)->value('id');

                if ($roleId === null) {
                    $roleId = DB::table('roles')->insertGetId(['role_name' => $roleName]);
                }

                $password = Str::random(32);

                $accountData = [
                    'role_id' => $roleId,
                    'password_hash' => Hash::make($password),
                    'is_active' => true,
                ];

                if ($existingAccounts->has($email)) {
                    DB::table('users')->where('email', $email)->update($accountData);
                } else {
                    DB::table('users')->insert($accountData + [
                        'email' => $email,
                        'phone_number' => null,
                        'created_at' => now(),
                    ]);
                }

                $credentials[$roleName] = [
                    'email' => $email,
                    'password' => $password,
                ];
            }

            return $credentials;
        });

        foreach ($credentials as $roleName => $credential) {
            $this->line($roleName.': '.$credential['email']);
            $this->line('Kata sandi sementara (ditampilkan sekali): '.$credential['password']);
        }

        $this->warn('Simpan kata sandi dengan aman; kata sandi ini tidak akan ditampilkan lagi.');

        return self::SUCCESS;
    }
}
