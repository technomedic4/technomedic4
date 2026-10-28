<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inpatient_nursing_treatments', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('admission_id');
            $table->integer('patient_id');
            $table->string('treatment_name', 150);
            $table->text('notes')->nullable();
            $table->integer('recorded_by_user_id');
            $table->unsignedInteger('bill_item_id')->nullable()->unique();
            $table->timestamps();

            $table->index(['admission_id', 'created_at'], 'idx_nursing_treatments_admission_created');
            $table->foreign('admission_id', 'fk_nursing_treatments_admission')
                ->references('id')
                ->on('inpatient_admissions')
                ->restrictOnDelete();
            $table->foreign('patient_id', 'fk_nursing_treatments_patient')
                ->references('id')
                ->on('patient_profiles')
                ->restrictOnDelete();
            $table->foreign('recorded_by_user_id', 'fk_nursing_treatments_user')
                ->references('id')
                ->on('users')
                ->restrictOnDelete();
            $table->foreign('bill_item_id', 'fk_nursing_treatments_bill_item')
                ->references('id')
                ->on('bill_items')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inpatient_nursing_treatments');
    }
};
