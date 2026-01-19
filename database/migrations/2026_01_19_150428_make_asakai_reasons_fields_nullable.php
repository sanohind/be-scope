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
        Schema::table('asakai_reasons', function (Blueprint $table) {
            $table->string('part_no')->nullable()->change();
            $table->string('part_name')->nullable()->change();
            $table->text('problem')->nullable()->change();
            $table->integer('qty')->nullable()->change();
            $table->string('section')->nullable()->change(); // Using string to handle enum changes easier or keep enum if supported
            $table->string('line')->nullable()->change();
            $table->text('penyebab')->nullable()->change();
            $table->text('perbaikan')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('asakai_reasons', function (Blueprint $table) {
            // Note: Reverting enum column might be tricky depending on DB, using simple revert logic
            $table->string('part_no')->nullable(false)->change();
            $table->string('part_name')->nullable(false)->change();
            $table->text('problem')->nullable(false)->change();
            $table->integer('qty')->nullable(false)->change();
            $table->string('section')->nullable(false)->change();
            $table->string('line')->nullable(false)->change();
            $table->text('penyebab')->nullable(false)->change();
            $table->text('perbaikan')->nullable(false)->change();
        });
    }
};
