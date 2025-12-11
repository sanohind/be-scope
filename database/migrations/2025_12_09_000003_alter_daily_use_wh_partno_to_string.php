<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // For SQL Server, we need to drop and recreate the column
        // For MySQL, we can use MODIFY
        $driver = DB::getDriverName();

        if ($driver === 'sqlsrv') {
            // SQL Server: Drop and recreate column
            Schema::table('daily_use_wh', function (Blueprint $table) {
                $table->dropColumn('partno');
            });

            Schema::table('daily_use_wh', function (Blueprint $table) {
                $table->string('partno', 100)->nullable()->after('id');
            });
        } else {
            // MySQL/MariaDB: Modify column
            Schema::table('daily_use_wh', function (Blueprint $table) {
                $table->string('partno', 100)->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'sqlsrv') {
            Schema::table('daily_use_wh', function (Blueprint $table) {
                $table->dropColumn('partno');
            });

            Schema::table('daily_use_wh', function (Blueprint $table) {
                $table->integer('partno')->nullable()->after('id');
            });
        } else {
            Schema::table('daily_use_wh', function (Blueprint $table) {
                $table->integer('partno')->nullable()->change();
            });
        }
    }
};


