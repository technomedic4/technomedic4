<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inpatient_admissions', function (Blueprint $table): void {
            $table->increments('id');
            $table->integer('patient_id');
            $table->string('room_number', 30)->nullable();
            $table->string('bed_number', 30)->nullable();
            $table->text('admission_reason');
            $table->integer('admitted_by_user_id');
            $table->timestamp('admitted_at')->useCurrent();
            $table->integer('discharged_by_user_id')->nullable();
            $table->timestamp('discharged_at')->nullable();
            $table->timestamps();

            $table->foreign('patient_id', 'fk_inpatient_admissions_patients')
                ->references('id')
                ->on('patient_profiles')
                ->restrictOnDelete();
            $table->foreign('admitted_by_user_id', 'fk_inpatient_admissions_admitted_by')
                ->references('id')
                ->on('users')
                ->restrictOnDelete();
            $table->foreign('discharged_by_user_id', 'fk_inpatient_admissions_discharged_by')
                ->references('id')
                ->on('users')
                ->restrictOnDelete();
            $table->index(['discharged_at', 'admitted_at'], 'idx_inpatient_admissions_active');
        });

        Schema::table('patient_care_updates', function (Blueprint $table): void {
            $table->integer('appointment_id')->nullable()->change();
            $table->unsignedInteger('inpatient_admission_id')->nullable()->after('patient_id');
            $table->integer('recorded_by_user_id')->nullable()->after('inpatient_admission_id');
            $table->decimal('temperature_celsius', 4, 1)->nullable()->after('update_status');
            $table->string('blood_pressure', 15)->nullable()->after('temperature_celsius');
            $table->unsignedSmallInteger('pulse_rate')->nullable()->after('blood_pressure');
            $table->unsignedSmallInteger('respiratory_rate')->nullable()->after('pulse_rate');
            $table->unsignedTinyInteger('oxygen_saturation')->nullable()->after('respiratory_rate');

            $table->foreign('inpatient_admission_id', 'fk_patient_care_updates_admission')
                ->references('id')
                ->on('inpatient_admissions')
                ->restrictOnDelete();
            $table->foreign('recorded_by_user_id', 'fk_patient_care_updates_user')
                ->references('id')
                ->on('users')
                ->restrictOnDelete();
            $table->index(['inpatient_admission_id', 'created_at'], 'idx_care_updates_admission_created');
        });
    }

    public function down(): void
    {
        Schema::table('patient_care_updates', function (Blueprint $table): void {
            $table->dropForeign('fk_patient_care_updates_admission');
            $table->dropForeign('fk_patient_care_updates_user');
            $table->dropIndex('idx_care_updates_admission_created');
            $table->dropColumn([
                'inpatient_admission_id',
                'recorded_by_user_id',
                'temperature_celsius',
                'blood_pressure',
                'pulse_rate',
                'respiratory_rate',
                'oxygen_saturation',
            ]);
        });

        Schema::dropIfExists('inpatient_admissions');
    }
};
