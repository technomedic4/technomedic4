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
        Schema::create('bills', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('bill_number', 30)->unique();
            $table->integer('patient_id');
            $table->unsignedBigInteger('total_amount');
            $table->unsignedBigInteger('amount_paid')->default(0);
            $table->string('status', 20)->default('Unpaid');
            $table->integer('created_by_user_id');
            $table->timestamps();

            $table->index(['status', 'created_at'], 'idx_bills_status_created');
            $table->index(['patient_id', 'created_at'], 'idx_bills_patient_created');
            $table->foreign('patient_id', 'fk_bills_patient')
                ->references('id')
                ->on('patient_profiles')
                ->restrictOnDelete();
            $table->foreign('created_by_user_id', 'fk_bills_created_by')
                ->references('id')
                ->on('users')
                ->restrictOnDelete();
        });

        Schema::create('bill_items', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('bill_id');
            $table->string('item_name', 150);
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('unit_price');
            $table->timestamps();

            $table->foreign('bill_id', 'fk_bill_items_bill')
                ->references('id')
                ->on('bills')
                ->cascadeOnDelete();
        });

        Schema::create('bill_payments', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('bill_id');
            $table->integer('recorded_by_user_id');
            $table->unsignedBigInteger('amount');
            $table->string('payment_method', 30);
            $table->string('reference', 100)->nullable();
            $table->timestamp('paid_at');
            $table->timestamps();

            $table->index(['bill_id', 'paid_at'], 'idx_bill_payments_bill_paid');
            $table->index('paid_at', 'idx_bill_payments_paid');
            $table->foreign('bill_id', 'fk_bill_payments_bill')
                ->references('id')
                ->on('bills')
                ->cascadeOnDelete();
            $table->foreign('recorded_by_user_id', 'fk_bill_payments_recorded_by')
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
        Schema::dropIfExists('bill_payments');
        Schema::dropIfExists('bill_items');
        Schema::dropIfExists('bills');
    }
};
