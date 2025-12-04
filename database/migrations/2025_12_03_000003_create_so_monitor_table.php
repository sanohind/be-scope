<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('so_monitor', function (Blueprint $table) {
            $table->id();
            $table->integer('year')->nullable();
            $table->integer('period')->nullable();
            $table->string('bp_code')->nullable();
            $table->string('bp_name')->nullable();
            $table->decimal('total_po', 18, 2)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('so_monitor');
    }
};
