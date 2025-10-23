<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('data_receipt_purchase', function (Blueprint $table) {
            $table->id();
            $table->string('po_no')->nullable();
            $table->string('bp_id')->nullable();
            $table->string('bp_name')->nullable();
            $table->string('currency')->nullable();
            $table->string('po_type')->nullable();
            $table->string('po_reference')->nullable();
            $table->string('po_line')->nullable();
            $table->string('po_sequence')->nullable();
            $table->string('po_receipt_sequence')->nullable();
            $table->date('actual_receipt_date')->nullable();
            $table->string('actual_receipt_year')->nullable();
            $table->string('actual_receipt_period')->nullable();
            $table->string('receipt_no')->nullable();
            $table->string('receipt_line')->nullable();
            $table->string('gr_no')->nullable();
            $table->string('packing_slip')->nullable();
            $table->string('item_no')->nullable();
            $table->string('ics_code')->nullable();
            $table->string('ics_part')->nullable();
            $table->string('part_no')->nullable();
            $table->string('item_desc')->nullable();
            $table->string('item_group')->nullable();
            $table->string('item_type')->nullable();
            $table->string('item_type_desc')->nullable();
            $table->decimal('request_qty', 18, 2)->nullable();
            $table->decimal('actual_receipt_qty', 18, 2)->nullable();
            $table->decimal('approve_qty', 18, 2)->nullable();
            $table->string('unit')->nullable();
            $table->decimal('receipt_amount', 18, 2)->nullable();
            $table->decimal('receipt_unit_price', 18, 2)->nullable();
            $table->boolean('is_final_receipt')->nullable();
            $table->boolean('is_confirmed')->nullable();
            $table->string('inv_doc_no')->nullable();
            $table->date('inv_doc_date')->nullable();
            $table->decimal('inv_qty', 18, 2)->nullable();
            $table->decimal('inv_amount', 18, 2)->nullable();
            $table->string('inv_supplier_no')->nullable();
            $table->date('inv_due_date')->nullable();
            $table->string('payment_doc')->nullable();
            $table->date('payment_doc_date')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_receipt_purchase');
    }
};
