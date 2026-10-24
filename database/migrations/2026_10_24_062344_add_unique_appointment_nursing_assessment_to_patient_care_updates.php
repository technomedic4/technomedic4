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
        Schema::table('patient_care_updates', function (Blueprint $table): void {
            $table->unique('appointment_id', 'uq_care_updates_appointment');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('patient_care_updates', function (Blueprint $table): void {
            $table->dropUnique('uq_care_updates_appointment');
        });
    }
};
