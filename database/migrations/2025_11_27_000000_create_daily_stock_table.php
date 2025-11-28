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
        Schema::create('daily_stock', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('warehouse', 50);
            $table->string('partno', 100);
            $table->bigInteger('onhand');
            $table->date('date');
            $table->timestamps();

            $table->unique(['warehouse', 'partno', 'date'], 'daily_stock_unique_entry');
            $table->index(['warehouse', 'partno', 'date'], 'daily_stock_wh_part_date_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daily_stock');
    }
};

