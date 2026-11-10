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
        DB::table('roles')->insertOrIgnore([
            ['role_name' => 'Laboratorium'],
        ]);

        Schema::create('laboratory_orders', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('consultation_id')->unique();
            $table->integer('patient_id');
            $table->integer('referring_doctor_id');
            $table->text('requested_tests');
            $table->text('clinical_notes')->nullable();
            $table->string('status', 20)->default('Pending');
            $table->text('results')->nullable();
            $table->text('result_notes')->nullable();
            $table->integer('resulted_by_user_id')->nullable();
            $table->timestamp('resulted_at')->nullable();
            $table->timestamps();

            $table->foreign('consultation_id')
                ->references('id')
                ->on('doctor_consultations')
                ->restrictOnDelete();
            $table->foreign('patient_id')
                ->references('id')
                ->on('patient_profiles')
                ->restrictOnDelete();
            $table->foreign('referring_doctor_id')
                ->references('id')
                ->on('doctors')
                ->restrictOnDelete();
            $table->foreign('resulted_by_user_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('laboratory_orders');

        DB::table('roles')
            ->where('role_name', 'Laboratorium')
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('users')
                    ->whereColumn('users.role_id', 'roles.id');
            })
            ->delete();
    }
};
