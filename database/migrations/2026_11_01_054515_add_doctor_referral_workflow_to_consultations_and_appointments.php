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
        Schema::table('doctor_consultations', function (Blueprint $table): void {
            $table->integer('referral_doctor_id')->nullable()->after('referral_department_id');
            $table->text('referral_reason')->nullable()->after('referral_doctor_id');
            $table->text('examination_findings')->nullable()->after('referral_reason');
            $table->foreign('referral_doctor_id')->references('id')->on('doctors')->restrictOnDelete();
        });

        Schema::table('appointments', function (Blueprint $table): void {
            $table->unsignedInteger('referral_consultation_id')->nullable()->after('initial_complaint');
            $table->unique('referral_consultation_id');
            $table->foreign('referral_consultation_id')
                ->references('id')
                ->on('doctor_consultations')
                ->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table): void {
            $table->dropForeign(['referral_consultation_id']);
            $table->dropUnique(['referral_consultation_id']);
            $table->dropColumn('referral_consultation_id');
        });

        Schema::table('doctor_consultations', function (Blueprint $table): void {
            $table->dropForeign(['referral_doctor_id']);
            $table->dropColumn([
                'referral_doctor_id',
                'referral_reason',
                'examination_findings',
            ]);
        });
    }
};
