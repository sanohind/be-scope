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
        Schema::create('warehouse_stock_summaries', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('warehouse', 50);
            $table->string('granularity', 20)->default('daily');
            $table->dateTime('period_start');
            $table->dateTime('period_end');
            $table->bigInteger('onhand_total')->default(0);
            $table->bigInteger('receipt_total')->default(0);
            $table->bigInteger('issue_total')->default(0);
            $table->timestamps();

            $table->unique(['warehouse', 'granularity', 'period_start'], 'warehouse_stock_summaries_unique_period');
            $table->index(['period_start', 'period_end'], 'warehouse_stock_summaries_period_idx');
            $table->index(['warehouse', 'granularity'], 'warehouse_stock_summaries_wh_granularity_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('warehouse_stock_summaries');
    }
};
