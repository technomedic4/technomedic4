<?php

namespace Database\Seeders;

use App\Support\HospitalStaffRole;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

class GeneralPractitionerScheduleSeeder extends Seeder
{
    /**
     * @var array<int, array{day: string, name: string, email: string, phone: string, str: string}>
     */
    private const DOCTORS = [
        ['day' => 'Senin', 'name' => 'dr. Arif Nugraha', 'email' => 'dokter.umum.senin@hospital.com', 'phone' => '082111000001', 'str' => 'STR-DEMO-UMUM-001'],
        ['day' => 'Selasa', 'name' => 'dr. Bima Saputra', 'email' => 'dokter.umum.selasa@hospital.com', 'phone' => '082111000002', 'str' => 'STR-DEMO-UMUM-002'],
        ['day' => 'Rabu', 'name' => 'dr. Citra Maharani', 'email' => 'dokter.umum.rabu@hospital.com', 'phone' => '082111000003', 'str' => 'STR-DEMO-UMUM-003'],
        ['day' => 'Kamis', 'name' => 'dr. Dimas Pratama', 'email' => 'dokter.umum.kamis@hospital.com', 'phone' => '082111000004', 'str' => 'STR-DEMO-UMUM-004'],
        ['day' => 'Jumat', 'name' => 'dr. Eka Wulandari', 'email' => 'dokter.umum.jumat@hospital.com', 'phone' => '082111000005', 'str' => 'STR-DEMO-UMUM-005'],
        ['day' => 'Sabtu', 'name' => 'dr. Fajar Ramadhan', 'email' => 'dokter.umum.sabtu@hospital.com', 'phone' => '082111000006', 'str' => 'STR-DEMO-UMUM-006'],
        ['day' => 'Minggu', 'name' => 'dr. Gita Permata', 'email' => 'dokter.umum.minggu@hospital.com', 'phone' => '082111000007', 'str' => 'STR-DEMO-UMUM-007'],
    ];

    public function run(): void
    {
        DB::transaction(function (): void {
            $doctorRoleId = DB::table('roles')
                ->where('role_name', HospitalStaffRole::Doctor->value)
                ->value('id');

            if ($doctorRoleId === null) {
                $doctorRoleId = DB::table('roles')->insertGetId([
                    'role_name' => HospitalStaffRole::Doctor->value,
                ]);
            }

            $departmentId = DB::table('departments')
                ->where('name', 'Poli Umum')
                ->value('id');

            if ($departmentId === null) {
                $departmentId = DB::table('departments')->insertGetId([
                    'name' => 'Poli Umum',
                ]);
            }

            $temporaryPassword = Str::random(40);
            $createdAccountCount = 0;

            foreach (self::DOCTORS as $roster) {
                $user = DB::table('users')->where('email', $roster['email'])->first();

                if ($user !== null && (int) $user->role_id !== (int) $doctorRoleId) {
                    throw new RuntimeException("Email {$roster['email']} sudah digunakan akun non-dokter.");
                }

                if ($user === null) {
                    $userId = DB::table('users')->insertGetId([
                        'role_id' => $doctorRoleId,
                        'email' => $roster['email'],
                        'phone_number' => $roster['phone'],
                        'password_hash' => Hash::make($temporaryPassword),
                        'is_active' => true,
                        'created_at' => now(),
                    ]);
                    $createdAccountCount++;
                } else {
                    $userId = $user->id;
                }

                $doctor = DB::table('doctors')->where('user_id', $userId)->first();

                if ($doctor !== null && (int) $doctor->department_id !== (int) $departmentId) {
                    throw new RuntimeException("Akun {$roster['email']} sudah terhubung ke poli lain.");
                }

                if ($doctor === null) {
                    $doctorId = DB::table('doctors')->insertGetId([
                        'user_id' => $userId,
                        'department_id' => $departmentId,
                        'full_name' => $roster['name'],
                        'specialization' => 'Dokter Umum',
                        'str_number' => $roster['str'],
                    ]);
                } else {
                    $doctorId = $doctor->id;
                    DB::table('doctors')->where('id', $doctorId)->update([
                        'full_name' => $roster['name'],
                        'specialization' => 'Dokter Umum',
                        'str_number' => $roster['str'],
                    ]);
                }

                $schedule = DB::table('doctor_schedules')
                    ->where('doctor_id', $doctorId)
                    ->where('day_of_week', $roster['day'])
                    ->first();
                $scheduleData = [
                    'start_time' => '08:00:00',
                    'end_time' => '12:00:00',
                    'quota_limit' => 20,
                ];

                if ($schedule === null) {
                    DB::table('doctor_schedules')->insert($scheduleData + [
                        'doctor_id' => $doctorId,
                        'day_of_week' => $roster['day'],
                    ]);
                } else {
                    DB::table('doctor_schedules')
                        ->where('doctor_id', $doctorId)
                        ->where('day_of_week', $roster['day'])
                        ->update($scheduleData);
                }
            }

            if ($createdAccountCount > 0 && $this->command !== null && ! app()->environment('testing')) {
                $this->command->info('Kata sandi sementara akun dokter umum baru: '.$temporaryPassword);
                $this->command->warn('Simpan kata sandi sementara dengan aman dan ganti setelah login pertama.');
            }
        });
    }
}
