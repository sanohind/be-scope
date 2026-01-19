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
        Schema::create('asakai_reasons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asakai_chart_id')->constrained('asakai_charts')->onDelete('cascade');
            $table->date('date');
            $table->string('part_no');
            $table->string('part_name');
            $table->text('problem');
            $table->integer('qty');
            $table->enum('section', ['brazzing', 'chassis', 'nylon', 'subcon', 'passthrough']);
            $table->string('line');
            $table->text('penyebab');
            $table->text('perbaikan');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->timestamps();
            
            // Unique constraint: one reason per chart per date
            $table->unique(['asakai_chart_id', 'date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('asakai_reasons');
    }
};
