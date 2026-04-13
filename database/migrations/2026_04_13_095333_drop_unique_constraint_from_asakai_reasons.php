<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Drop the unique constraint on (asakai_chart_id, date) so that
     * multiple reasons can be added to the same chart and date.
     *
     * The unique index is used by a FK constraint, so we must:
     * 1. Drop the FK
     * 2. Drop the unique index
     * 3. Re-add the FK with a regular (non-unique) index
     */
    public function up(): void
    {
        Schema::table('asakai_reasons', function (Blueprint $table) {
            // 1. Drop the foreign key that references the unique index
            $table->dropForeign(['asakai_chart_id']);

            // 2. Drop the unique constraint
            $table->dropUnique('asakai_reasons_asakai_chart_id_date_unique');

            // 3. Add a plain index and re-create the foreign key
            $table->index('asakai_chart_id');
            $table->foreign('asakai_chart_id')
                  ->references('id')
                  ->on('asakai_charts')
                  ->onDelete('cascade');
        });
    }

    /**
     * Restore the unique constraint and original FK if rolling back.
     */
    public function down(): void
    {
        Schema::table('asakai_reasons', function (Blueprint $table) {
            $table->dropForeign(['asakai_chart_id']);
            $table->dropIndex(['asakai_chart_id']);
            $table->unique(['asakai_chart_id', 'date']);
            $table->foreign('asakai_chart_id')
                  ->references('id')
                  ->on('asakai_charts')
                  ->onDelete('cascade');
        });
    }
};
