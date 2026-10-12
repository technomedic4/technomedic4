<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('patient_profiles', function (Blueprint $table): void {
            $table->dropForeign('fk_patient_users');
            $table->unsignedInteger('user_id')->nullable()->change();
            $table->string('national_id', 16)->nullable()->unique();
            $table->string('phone_number', 20)->nullable();
            $table->string('emergency_contact', 20)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $hasReceptionistPatientData = DB::table('patient_profiles')
            ->where(function ($query): void {
                $query->whereNull('user_id')
                    ->orWhereNotNull('national_id')
                    ->orWhereNotNull('phone_number')
                    ->orWhereNotNull('emergency_contact');
            })
            ->exists();

        if ($hasReceptionistPatientData) {
            throw new \RuntimeException(
                'Cannot roll back receptionist patient fields while patient records use them.'
            );
        }

        Schema::table('patient_profiles', function (Blueprint $table): void {
            $table->dropUnique('patient_profiles_national_id_unique');
            $table->dropColumn(['national_id', 'phone_number', 'emergency_contact']);
            $table->unsignedInteger('user_id')->nullable(false)->change();
            $table->foreign('user_id', 'fk_patient_users')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();
        });
    }
};
