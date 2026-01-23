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
        Schema::create('asakai_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asakai_title_id')->constrained('asakai_titles')->onDelete('cascade');
            $table->integer('year');
            $table->integer('period'); // 1-12 for months
            $table->decimal('target', 10, 2)->default(0);
            $table->timestamps();

            // Unique constraint: one record per title + year + period
            $table->unique(['asakai_title_id', 'year', 'period'], 'unique_title_year_period');
            
            // Index for faster queries
            $table->index(['asakai_title_id', 'year', 'period']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('asakai_targets');
    }
};
