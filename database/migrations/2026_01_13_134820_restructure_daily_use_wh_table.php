<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Restructure daily_use_wh table to use year/period format instead of daily dates
     */
    public function up(): void
    {
        Schema::table('daily_use_wh', function (Blueprint $table) {
            // Drop old columns
            $table->dropColumn(['plan_date', 'daily_use']);
            
            // Add new columns
            $table->integer('year')->nullable()->after('warehouse');
            $table->integer('period')->nullable()->after('year'); // period = month (1-12)
            $table->integer('qty')->nullable()->after('period'); // qty that applies to all days in the period
            
            // Add index for better query performance
            $table->index(['partno', 'warehouse', 'year', 'period'], 'idx_daily_use_wh_lookup');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('daily_use_wh', function (Blueprint $table) {
            // Drop new columns
            $table->dropIndex('idx_daily_use_wh_lookup');
            $table->dropColumn(['year', 'period', 'qty']);
            
            // Restore old columns
            $table->date('plan_date')->nullable();
            $table->integer('daily_use')->nullable();
        });
    }
};
