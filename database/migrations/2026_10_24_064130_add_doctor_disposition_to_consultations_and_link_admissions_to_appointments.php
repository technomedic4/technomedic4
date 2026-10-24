<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('doctor_consultations', 'disposition')) {
            Schema::table('doctor_consultations', function (Blueprint $table): void {
                $table->string('disposition', 20)->nullable()->after('treatment_plan');
            });
        }

        if (Schema::hasColumn('inpatient_admissions', 'appointment_id')) {
            Schema::table('inpatient_admissions', function (Blueprint $table): void {
                $table->integer('appointment_id')->nullable()->change();
            });
        } else {
            Schema::table('inpatient_admissions', function (Blueprint $table): void {
                $table->integer('appointment_id')->nullable()->after('patient_id');
            });
        }

        if (! Schema::hasIndex('inpatient_admissions', 'uq_inpatient_admissions_appointment', 'unique')) {
            Schema::table('inpatient_admissions', function (Blueprint $table): void {
                $table->unique('appointment_id', 'uq_inpatient_admissions_appointment');
            });
        }

        if (! Schema::hasForeignKey('inpatient_admissions', 'fk_inpatient_admissions_appointment')) {
            Schema::table('inpatient_admissions', function (Blueprint $table): void {
                $table->foreign('appointment_id', 'fk_inpatient_admissions_appointment')
                    ->references('id')
                    ->on('appointments')
                    ->restrictOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inpatient_admissions', function (Blueprint $table): void {
            $table->dropForeign('fk_inpatient_admissions_appointment');
            $table->dropUnique('uq_inpatient_admissions_appointment');
            $table->dropColumn('appointment_id');
        });

        Schema::table('doctor_consultations', function (Blueprint $table): void {
            $table->dropColumn('disposition');
        });
    }
};
