<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('view_warehouse_order', function (Blueprint $table) {
            $table->id();
            $table->string('order_origin_code')->nullable();
            $table->string('order_origin')->nullable();
            $table->string('trx_type')->nullable();
            $table->date('order_date')->nullable();
            $table->date('plan_delivery_date')->nullable();
            $table->string('ship_from_type')->nullable();
            $table->string('ship_from')->nullable();
            $table->string('ship_from_desc')->nullable();
            $table->string('ship_to_type')->nullable();
            $table->string('ship_to')->nullable();
            $table->string('ship_to_desc')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('view_warehouse_order');
    }
};
