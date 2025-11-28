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
        Schema::create('prod_report', function (Blueprint $table) {
            $table->id();
            $table->integer('prod_index')->nullable();
            $table->string('production_order')->nullable();
            $table->string('part_number')->nullable();
            $table->string('old_part')->nullable();
            $table->string('divisi')->nullable();
            $table->string('lot_number')->nullable();
            $table->decimal('qty_pelaporan', 18, 2)->nullable();
            $table->decimal('snp', 18, 2)->nullable();
            $table->integer('trans_date_month')->nullable();
            $table->integer('trans_date_year')->nullable();
            $table->decimal('qty_planning', 18, 2)->nullable();
            $table->date('planning_date')->nullable();
            $table->date('transaction_date')->nullable();
            $table->date('trans_date')->nullable();
            $table->string('Status')->nullable();
            $table->string('user_login')->nullable();
            $table->string('user name')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('prod_report');
    }
};
