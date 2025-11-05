<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('so_invoice_line_2', function (Blueprint $table) {
            $table->id();
            $table->string('bp_code')->nullable();
            $table->string('bp_name')->nullable();
            $table->string('sales_order')->nullable();
            $table->date('so_date')->nullable();
            $table->string('so_line')->nullable();
            $table->string('so_sequence')->nullable();
            $table->string('customer_po')->nullable();
            $table->string('shipment')->nullable();
            $table->string('shipment_line')->nullable();
            $table->date('delivery_date')->nullable();
            $table->string('part_no')->nullable();
            $table->string('old_partno')->nullable();
            $table->string('product_type')->nullable();
            $table->string('cust_partno')->nullable();
            $table->string('cust_partname')->nullable();
            $table->decimal('delivered_qty', 18, 2)->nullable();
            $table->string('unit')->nullable();
            $table->string('shipment_reference')->nullable();
            $table->string('status')->nullable();
            $table->string('shipment_status')->nullable();
            $table->string('invoice_no')->nullable();
            $table->string('inv_line')->nullable();
            $table->date('invoice_date')->nullable();
            $table->decimal('invoice_qty', 18, 2)->nullable();
            $table->string('currency')->nullable();
            $table->decimal('price', 18, 2)->nullable();
            $table->decimal('amount', 18, 2)->nullable();
            $table->decimal('price_hc', 18, 2)->nullable();
            $table->decimal('amount_hc', 18, 2)->nullable();
            $table->string('inv_stat')->nullable();
            $table->string('invoice_status')->nullable();
            $table->date('dlv_log_date')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('so_invoice_line_2');
    }
};
