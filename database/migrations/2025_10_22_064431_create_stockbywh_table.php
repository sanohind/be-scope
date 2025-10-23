<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('stockbywh', function (Blueprint $table) {
            $table->id();
            $table->string('warehouse')->nullable();
            $table->string('partno')->nullable();
            $table->string('desc')->nullable();
            $table->string('partname')->nullable();
            $table->string('oldpartno')->nullable();
            $table->string('group')->nullable();
            $table->string('groupkey')->nullable();
            $table->string('product_type')->nullable();
            $table->string('model')->nullable();
            $table->string('customer')->nullable();
            $table->decimal('onhand', 18, 2)->nullable();
            $table->decimal('allocated', 18, 2)->nullable();
            $table->decimal('onorder', 18, 2)->nullable();
            $table->decimal('economicstock', 18, 2)->nullable();
            $table->decimal('safety_stock', 18, 2)->nullable();
            $table->decimal('min_stock', 18, 2)->nullable();
            $table->decimal('max_stock', 18, 2)->nullable();
            $table->string('unit')->nullable();
            $table->string('location')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stockbywh');
    }
};
