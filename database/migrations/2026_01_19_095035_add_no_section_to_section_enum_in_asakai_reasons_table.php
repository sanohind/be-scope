<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('asakai_reasons', function (Blueprint $table) {
            DB::statement("ALTER TABLE asakai_reasons MODIFY COLUMN section ENUM('brazzing', 'chassis', 'nylon', 'subcon', 'passthrough', 'no_section') NOT NULL");
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('asakai_reasons', function (Blueprint $table) {
            DB::statement("ALTER TABLE asakai_reasons MODIFY COLUMN section ENUM('brazzing', 'chassis', 'nylon', 'subcon', 'passthrough') NOT NULL");
        });
    }
};
