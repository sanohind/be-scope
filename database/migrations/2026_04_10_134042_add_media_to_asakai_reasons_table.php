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
            $table->string('image_path')->nullable()->after('perbaikan');
            $table->string('document_path')->nullable()->after('image_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('asakai_reasons', function (Blueprint $table) {
            $table->dropColumn(['image_path', 'document_path']);
        });
    }
};
