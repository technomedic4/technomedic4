<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mobile_payment_submissions', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('bill_id');
            $table->integer('submitted_by_user_id');
            $table->integer('reviewed_by_user_id')->nullable();
            $table->unsignedBigInteger('amount');
            $table->string('payment_method', 20);
            $table->string('reference', 100)->nullable();
            $table->string('proof_path', 500);
            $table->string('status', 20)->default('Pending');
            $table->text('review_note')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['bill_id', 'status'], 'idx_mobile_payment_bill_status');
            $table->foreign('bill_id', 'fk_mobile_payment_bill')
                ->references('id')
                ->on('bills')
                ->cascadeOnDelete();
            $table->foreign('submitted_by_user_id', 'fk_mobile_payment_submitter')
                ->references('id')
                ->on('users')
                ->restrictOnDelete();
            $table->foreign('reviewed_by_user_id', 'fk_mobile_payment_reviewer')
                ->references('id')
                ->on('users')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mobile_payment_submissions');
    }
};
