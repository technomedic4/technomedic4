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
        Schema::create('laboratory_results', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('laboratory_order_id');
            $table->string('parameter_code', 60);
            $table->string('parameter_name', 150);
            $table->string('result_value', 100);
            $table->string('unit', 40)->nullable();
            $table->string('reference_range', 100)->nullable();
            $table->string('interpretation', 20);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('laboratory_order_id')
                ->references('id')
                ->on('laboratory_orders')
                ->cascadeOnDelete();
            $table->unique(['laboratory_order_id', 'parameter_code'], 'laboratory_results_order_parameter_unique');
            $table->index(['laboratory_order_id', 'sort_order'], 'laboratory_results_order_sort_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('laboratory_results');
    }
};
