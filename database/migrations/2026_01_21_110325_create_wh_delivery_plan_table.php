<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wh_delivery_plan', function (Blueprint $table) {
            $table->id();
            $table->string('partno')->nullable();
            $table->string('warehouse')->nullable();
            $table->integer('qty_delivery')->nullable();
            $table->date('delivery_date')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wh_delivery_plan');
    }
};
