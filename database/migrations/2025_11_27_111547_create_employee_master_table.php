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
        Schema::create('employee_master', function (Blueprint $table) {
            $table->id();
            $table->integer('user_id')->nullable();
            $table->string('first_name')->nullable();
            $table->string('middle_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('user_name')->nullable();
            $table->string('company_name')->nullable();
            $table->string('full_name')->nullable();
            $table->string('emp_id')->nullable()->unique();
            $table->string('emp_no')->nullable();
            $table->integer('position_id')->nullable();
            $table->string('pos_code')->nullable();
            $table->string('pos_name_en')->nullable();
            $table->string('employment_status_code')->nullable();
            $table->string('employment_status')->nullable();
            $table->string('email')->nullable();
            $table->integer('company_id')->nullable();
            $table->string('spv_parent')->nullable();
            $table->string('spv_path')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('photo')->nullable();
            $table->text('address')->nullable();
            $table->string('phone')->nullable();
            $table->string('job_status')->nullable();
            $table->boolean('email_verified')->default(false);
            $table->string('worklocation_code')->nullable();
            $table->string('worklocation_name')->nullable();
            $table->string('cost_code')->nullable();
            $table->string('costcenter_name')->nullable();
            $table->string('dept_code')->nullable();
            $table->string('dept_name_en')->nullable();
            $table->string('org_unit')->nullable();
            $table->date('employment_start_date')->nullable();
            $table->date('employment_end_date')->nullable();
            $table->string('customfield1')->nullable();
            $table->string('customfield2')->nullable();
            $table->string('customfield3')->nullable();
            $table->string('customfield4')->nullable();
            $table->string('customfield5')->nullable();
            $table->string('customfield6')->nullable();
            $table->string('customfield7')->nullable();
            $table->string('customfield8')->nullable();
            $table->string('customfield9')->nullable();
            $table->string('customfield10')->nullable();
            $table->timestamps();

            $table->index('emp_id');
            $table->index('emp_no');
            $table->index('company_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_master');
    }
};
