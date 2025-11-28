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
        Schema::create('attendance_by_period', function (Blueprint $table) {
            $table->id();
            $table->string('attend_id')->unique();
            $table->string('emp_id')->nullable();
            $table->string('shiftdaily_code')->nullable();
            $table->integer('company_id')->nullable();
            $table->dateTime('shiftstarttime')->nullable();
            $table->dateTime('shiftendtime')->nullable();
            $table->string('attend_code')->nullable();
            $table->dateTime('starttime')->nullable();
            $table->dateTime('endtime')->nullable();
            $table->integer('actual_in')->nullable();
            $table->integer('actual_out')->nullable();
            $table->string('daytype')->nullable();
            $table->string('ip_starttime')->nullable();
            $table->string('ip_endtime')->nullable();
            $table->text('remark')->nullable();
            $table->string('default_shift')->nullable();
            $table->decimal('total_ot', 18, 2)->nullable();
            $table->decimal('total_otindex', 18, 2)->nullable();
            $table->string('overtime_code')->nullable();
            $table->string('flexibleshift')->nullable();
            $table->boolean('auto_ovt')->nullable();
            $table->integer('actualworkmnt')->nullable();
            $table->integer('actual_lti')->nullable();
            $table->integer('actual_eao')->nullable();
            $table->string('geolocation')->nullable();
            $table->string('geoloc_start')->nullable();
            $table->string('geoloc_end')->nullable();
            $table->string('photo_start')->nullable();
            $table->string('photo_end')->nullable();
            $table->string('emp_no')->nullable();
            $table->string('spv_no')->nullable();
            $table->string('spv_id')->nullable();
            $table->string('pos_name_en')->nullable();
            $table->string('pos_name_id')->nullable();
            $table->string('pos_name_my')->nullable();
            $table->string('pos_name_th')->nullable();
            $table->timestamps();

            $table->index('emp_id');
            $table->index('company_id');
            $table->index('starttime');
            $table->index('endtime');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendance_by_period');
    }
};
