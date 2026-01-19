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
        Schema::create('daily_use_wh_min_max', function (Blueprint $table) {
            $table->id();
            $table->string('warehouse', 50);
            $table->integer('year');
            $table->integer('period'); // 1-12 for months
            $table->integer('min_onhand')->default(0);
            $table->integer('max_onhand')->default(0);
            $table->timestamps();

            // Unique constraint: one record per warehouse + year + period
            $table->unique(['warehouse', 'year', 'period'], 'unique_wh_year_period');
            
            // Index for faster queries
            $table->index(['warehouse', 'year', 'period']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daily_use_wh_min_max');
    }
};
