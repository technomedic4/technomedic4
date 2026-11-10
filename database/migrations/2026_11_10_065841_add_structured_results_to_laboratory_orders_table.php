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
        Schema::table('laboratory_orders', function (Blueprint $table): void {
            $table->json('requested_test_codes')->nullable()->after('requested_tests');
            $table->index(['status', 'created_at'], 'laboratory_orders_status_created_at_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('laboratory_orders', function (Blueprint $table): void {
            $table->dropIndex('laboratory_orders_status_created_at_index');
            $table->dropColumn('requested_test_codes');
        });
    }
};
