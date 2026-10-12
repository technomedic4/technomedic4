<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table): void {
            $table->string('consultation_type', 30)->default('In-Person');
        });

        Schema::create('teleconsultation_messages', function (Blueprint $table): void {
            $table->increments('id');
            $table->integer('appointment_id');
            $table->integer('sender_user_id');
            $table->text('message');
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('appointment_id', 'fk_teleconsult_messages_appointments')
                ->references('id')
                ->on('appointments')
                ->cascadeOnDelete();
            $table->foreign('sender_user_id', 'fk_teleconsult_messages_users')
                ->references('id')
                ->on('users')
                ->restrictOnDelete();
            $table->index(['appointment_id', 'id'], 'idx_teleconsult_messages_conversation');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teleconsultation_messages');

        Schema::table('appointments', function (Blueprint $table): void {
            $table->dropColumn('consultation_type');
        });
    }
};
