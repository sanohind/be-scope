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
        Schema::dropIfExists('attendance_by_status');

        Schema::create('attendance_by_status', function (Blueprint $table) {
            $table->id();

            // Unique identifier: employee + period
            $table->string('emp_no')->nullable()->index();
            $table->date('period_start')->nullable()->index();
            $table->date('period_end')->nullable();

            // Employee info
            $table->string('emp_first_name')->nullable();
            $table->string('emp_middle_name')->nullable();
            $table->string('emp_last_name')->nullable();
            $table->date('birth_date')->nullable();
            $table->string('position_name')->nullable();
            $table->string('department')->nullable();
            $table->date('emp_start_date')->nullable();
            $table->date('emp_end_date')->nullable();
            $table->string('cost_code')->nullable();
            $table->string('spv_name')->nullable();
            $table->string('emp_status')->nullable();
            $table->integer('contracted_work_hours')->nullable();
            $table->string('gender')->nullable();

            // Attendance status counts (dynamic, stored as JSON)
            $table->json('attendance_status')->nullable();

            // Catch-all for raw API response
            $table->json('raw_data')->nullable();

            $table->timestamps();

            // Unique constraint: one record per employee per period
            $table->unique(['emp_no', 'period_start'], 'unique_emp_period');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendance_by_status');
    }
};
