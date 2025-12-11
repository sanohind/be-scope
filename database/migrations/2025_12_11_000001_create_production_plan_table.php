<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_plan', function (Blueprint $table) {
            $table->id();
            $table->string('partno')->nullable();
            $table->string('divisi')->nullable();
            $table->integer('qty_plan')->nullable();
            $table->date('plan_date')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_plan');
    }
};
