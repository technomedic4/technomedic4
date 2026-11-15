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
        Schema::table('inpatient_admissions', function (Blueprint $table): void {
            $table->integer('doctor_discharge_approved_by_user_id')->nullable()->after('discharged_at');
            $table->timestamp('doctor_discharge_approved_at')->nullable()->after('doctor_discharge_approved_by_user_id');

            $table->foreign('doctor_discharge_approved_by_user_id', 'fk_inpatient_admissions_discharge_approved_by')
                ->references('id')
                ->on('users')
                ->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inpatient_admissions', function (Blueprint $table): void {
            $table->dropForeign('fk_inpatient_admissions_discharge_approved_by');
            $table->dropColumn([
                'doctor_discharge_approved_by_user_id',
                'doctor_discharge_approved_at',
            ]);
        });
    }
};
