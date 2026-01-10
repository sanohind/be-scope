<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('daily_use_wh', function (Blueprint $table) {
            $table->string('warehouse', 50)->nullable()->after('partno');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('daily_use_wh', function (Blueprint $table) {
            $table->dropColumn('warehouse');
        });
    }
};
