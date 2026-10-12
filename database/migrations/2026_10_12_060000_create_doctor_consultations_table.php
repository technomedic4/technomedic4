<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('doctor_consultations', function (Blueprint $table): void {
            $table->increments('id');
            $table->integer('appointment_id')->unique();
            $table->integer('patient_id');
            $table->integer('doctor_id');
            $table->text('diagnosis');
            $table->text('treatment_plan');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('appointment_id', 'fk_consultations_appointments')
                ->references('id')
                ->on('appointments')
                ->restrictOnDelete();
            $table->foreign('patient_id', 'fk_consultations_patients')
                ->references('id')
                ->on('patient_profiles')
                ->restrictOnDelete();
            $table->foreign('doctor_id', 'fk_consultations_doctors')
                ->references('id')
                ->on('doctors')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('doctor_consultations');
    }
};
