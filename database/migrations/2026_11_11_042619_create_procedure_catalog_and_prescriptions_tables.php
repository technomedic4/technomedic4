<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('procedure_catalog', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('category', 120);
            $table->string('name', 180);
            $table->unsignedBigInteger('price');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['category', 'name'], 'uq_procedure_catalog_category_name');
        });

        Schema::create('medicine_catalog', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('code', 100)->unique();
            $table->string('generic_name', 180);
            $table->string('strength', 100)->nullable();
            $table->string('dosage_form', 100)->nullable();
            $table->string('unit', 50);
            $table->unsignedBigInteger('price');
            $table->boolean('price_is_estimate')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['generic_name', 'is_active'], 'idx_medicine_catalog_name_active');
        });

        Schema::table('bills', function (Blueprint $table): void {
            $table->unsignedInteger('source_consultation_id')->nullable()->unique('uq_bills_source_consultation');
            $table->foreign('source_consultation_id', 'fk_bills_source_consultation')
                ->references('id')
                ->on('doctor_consultations')
                ->restrictOnDelete();
        });

        Schema::table('bill_items', function (Blueprint $table): void {
            $table->unsignedInteger('procedure_catalog_id')->nullable();
            $table->unsignedInteger('medicine_catalog_id')->nullable();

            $table->foreign('procedure_catalog_id', 'fk_bill_items_procedure_catalog')
                ->references('id')
                ->on('procedure_catalog')
                ->restrictOnDelete();
            $table->foreign('medicine_catalog_id', 'fk_bill_items_medicine_catalog')
                ->references('id')
                ->on('medicine_catalog')
                ->restrictOnDelete();
        });

        Schema::create('prescriptions', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('consultation_id')->unique();
            $table->integer('patient_id');
            $table->integer('doctor_id');
            $table->unsignedInteger('bill_id');
            $table->integer('dispensed_by_user_id')->nullable();
            $table->timestamp('dispensed_at')->nullable();
            $table->timestamps();

            $table->foreign('consultation_id', 'fk_prescriptions_consultation')
                ->references('id')
                ->on('doctor_consultations')
                ->restrictOnDelete();
            $table->foreign('patient_id', 'fk_prescriptions_patient')
                ->references('id')
                ->on('patient_profiles')
                ->restrictOnDelete();
            $table->foreign('doctor_id', 'fk_prescriptions_doctor')
                ->references('id')
                ->on('doctors')
                ->restrictOnDelete();
            $table->foreign('bill_id', 'fk_prescriptions_bill')
                ->references('id')
                ->on('bills')
                ->restrictOnDelete();
            $table->foreign('dispensed_by_user_id', 'fk_prescriptions_dispensed_by')
                ->references('id')
                ->on('users')
                ->restrictOnDelete();

            $table->index(['bill_id', 'dispensed_at'], 'idx_prescriptions_bill_dispensed');
        });

        Schema::create('prescription_items', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('prescription_id');
            $table->unsignedInteger('medicine_id');
            $table->string('medicine_name', 180);
            $table->string('strength', 100)->nullable();
            $table->string('dosage_form', 100)->nullable();
            $table->string('unit', 50);
            $table->unsignedInteger('quantity');
            $table->string('dosage', 150);
            $table->text('instructions');
            $table->unsignedBigInteger('unit_price');
            $table->timestamps();

            $table->foreign('prescription_id', 'fk_prescription_items_prescription')
                ->references('id')
                ->on('prescriptions')
                ->cascadeOnDelete();
            $table->foreign('medicine_id', 'fk_prescription_items_medicine')
                ->references('id')
                ->on('medicine_catalog')
                ->restrictOnDelete();
        });

        DB::table('roles')->insertOrIgnore([
            'role_name' => 'Farmasi',
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('prescription_items');
        Schema::dropIfExists('prescriptions');

        Schema::table('bill_items', function (Blueprint $table): void {
            $table->dropForeign('fk_bill_items_procedure_catalog');
            $table->dropForeign('fk_bill_items_medicine_catalog');
            $table->dropColumn(['procedure_catalog_id', 'medicine_catalog_id']);
        });

        Schema::table('bills', function (Blueprint $table): void {
            $table->dropForeign('fk_bills_source_consultation');
            $table->dropUnique('uq_bills_source_consultation');
            $table->dropColumn('source_consultation_id');
        });

        Schema::dropIfExists('medicine_catalog');
        Schema::dropIfExists('procedure_catalog');

        DB::table('roles')
            ->where('role_name', 'Farmasi')
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('users')
                    ->whereColumn('users.role_id', 'roles.id');
            })
            ->delete();
    }
};
