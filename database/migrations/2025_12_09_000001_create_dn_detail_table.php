<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dn_detail', function (Blueprint $table) {
            $table->id();
            $table->string('no_dn')->nullable();
            $table->integer('dn_line')->nullable();
            $table->string('dn_supplier')->nullable();
            $table->dateTime('dn_create_date')->nullable();
            $table->integer('dn_year')->nullable();
            $table->integer('dn_period')->nullable();
            $table->date('plan_delivery_date')->nullable();
            $table->integer('plan_delivery_time')->nullable();
            $table->string('order_origin')->nullable();
            $table->string('no_order')->nullable();
            $table->string('order_set')->nullable();
            $table->string('order_line')->nullable();
            $table->string('order_seq')->nullable();
            $table->string('part_no')->nullable();
            $table->string('item_desc_a')->nullable();
            $table->string('item_desc_b')->nullable();
            $table->string('supplier_item_no')->nullable();
            $table->string('lot_number')->nullable();
            $table->integer('dn_qty')->nullable();
            $table->integer('receipt_qty')->nullable();
            $table->string('dn_unit')->nullable();
            $table->integer('dn_snp')->nullable();
            $table->string('reference')->nullable();
            $table->date('actual_receipt_date')->nullable();
            $table->integer('actual_receipt_time')->nullable();
            $table->string('warehouse')->nullable();
            $table->string('status_code')->nullable();
            $table->string('status_desc')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dn_detail');
    }
};

