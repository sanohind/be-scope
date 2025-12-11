<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_use_wh', function (Blueprint $table) {
            $table->id();
            $table->integer('partno')->nullable();
            $table->integer('daily_use')->nullable();
            $table->date('plan_date')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_use_wh');
    }
};

