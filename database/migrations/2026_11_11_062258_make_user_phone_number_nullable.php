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
        Schema::table('users', function (Blueprint $table): void {
            $table->string('phone_number', 20)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::table('users')->whereNull('phone_number')->exists()) {
            throw new RuntimeException('Cannot require phone numbers while staff accounts have no phone number.');
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->string('phone_number', 20)->nullable(false)->change();
        });
    }
};
