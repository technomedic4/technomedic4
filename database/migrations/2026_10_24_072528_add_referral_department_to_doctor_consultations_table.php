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
            $table->integer('referral_department_id')->nullable()->after('disposition');
            $table->foreign('referral_department_id', 'fk_consultations_referral_department')
                ->references('id')
                ->on('departments')
                ->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('doctor_consultations', function (Blueprint $table): void {
            $table->dropForeign('fk_consultations_referral_department');
            $table->dropColumn('referral_department_id');
        });
    }
};
