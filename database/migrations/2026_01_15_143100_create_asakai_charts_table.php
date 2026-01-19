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
        Schema::create('asakai_charts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asakai_title_id')->constrained('asakai_titles')->onDelete('cascade');
            $table->date('date');
            $table->integer('qty');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->timestamps();
            
            // Unique constraint: one chart per title per date
            $table->unique(['asakai_title_id', 'date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('asakai_charts');
    }
};
