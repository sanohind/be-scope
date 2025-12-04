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
        Schema::table('stock_by_wh_snapshots', function (Blueprint $table) {
            $table->string('group_type')->nullable()->after('groupkey');
            $table->string('group_type_desc')->nullable()->after('group_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stock_by_wh_snapshots', function (Blueprint $table) {
            $table->dropColumn(['group_type', 'group_type_desc']);
        });
    }
};

