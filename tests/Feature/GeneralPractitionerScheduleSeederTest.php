<?php

namespace Tests\Feature;

use Database\Seeders\GeneralPractitionerScheduleSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class GeneralPractitionerScheduleSeederTest extends TestCase
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
            $table->string('name')->unique();
        });

        Schema::create('doctors', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('user_id')->unique();
            $table->unsignedInteger('department_id');
            $table->string('full_name');
            $table->string('specialization');
            $table->string('str_number')->unique();
        });

        Schema::create('doctor_schedules', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('doctor_id');
            $table->string('day_of_week');
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedInteger('quota_limit');
        });
    }

    public function test_seeder_creates_a_different_general_practitioner_for_each_day_and_is_idempotent(): void
    {
        $this->seed(GeneralPractitionerScheduleSeeder::class);

        $doctors = DB::table('doctors')
            ->join('departments', 'departments.id', '=', 'doctors.department_id')
            ->where('departments.name', 'Poli Umum')
            ->where('doctors.specialization', 'Dokter Umum')
            ->get(['doctors.id', 'doctors.user_id']);
        $schedules = DB::table('doctor_schedules')->orderBy('day_of_week')->get();

        $this->assertCount(7, $doctors);
        $this->assertCount(7, $schedules);
        $this->assertCount(7, $doctors->pluck('user_id')->unique());
        $this->assertCount(7, $schedules->pluck('doctor_id')->unique());
        $this->assertSame(
            ['Jumat', 'Kamis', 'Minggu', 'Rabu', 'Sabtu', 'Selasa', 'Senin'],
            $schedules->pluck('day_of_week')->all(),
        );
        $this->assertSame(['08:00:00', '12:00:00', 20], [
            $schedules->first()->start_time,
            $schedules->first()->end_time,
            (int) $schedules->first()->quota_limit,
        ]);

        $this->seed(GeneralPractitionerScheduleSeeder::class);

        $this->assertDatabaseCount('users', 7);
        $this->assertDatabaseCount('doctors', 7);
        $this->assertDatabaseCount('doctor_schedules', 7);
    }
}
