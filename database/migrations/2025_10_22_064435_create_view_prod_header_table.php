<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('view_prod_header', function (Blueprint $table) {
            $table->id();
            $table->integer('prod_index')->nullable();
            $table->string('prod_no')->nullable();
            $table->date('planning_date')->nullable();
            $table->string('item')->nullable();
            $table->string('old_partno')->nullable();
            $table->string('description')->nullable();
            $table->string('mat_desc')->nullable();
            $table->string('customer')->nullable();
            $table->string('model')->nullable();
            $table->string('unique_no')->nullable();
            $table->string('sanoh_code')->nullable();
            $table->decimal('snp', 18, 2)->nullable();
            $table->string('sts')->nullable();
            $table->string('status')->nullable();
            $table->decimal('qty_order', 18, 2)->nullable();
            $table->decimal('qty_delivery', 18, 2)->nullable();
            $table->decimal('qty_os', 18, 2)->nullable();
            $table->string('warehouse')->nullable();
            $table->string('divisi')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('view_prod_header');
    }
};
