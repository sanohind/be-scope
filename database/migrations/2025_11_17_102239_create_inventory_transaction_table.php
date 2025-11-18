<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('inventory_transaction', function (Blueprint $table) {
            $table->id();
            $table->string('partno')->nullable();
            $table->string('part_desc')->nullable();
            $table->string('std_oldpart')->nullable();
            $table->string('warehouse')->nullable();
            $table->date('trans_date')->nullable();
            $table->date('trans_date2')->nullable();
            $table->string('lotno')->nullable();
            $table->string('trans_id')->nullable();
            $table->decimal('qty', 18, 2)->nullable();
            $table->decimal('qty_hand', 18, 2)->nullable();
            $table->string('trans_type')->nullable();
            $table->string('order_type')->nullable();
            $table->string('order_no')->nullable();
            $table->string('receipt')->nullable();
            $table->string('shipment')->nullable();
            $table->string('user')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_transaction');
    }
};
