<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeMaster extends Model
{
    protected $table = 'employee_master';
    public $timestamps = true;

    protected $fillable = [
        'user_id',
        'first_name',
        'middle_name',
        'last_name',
        'user_name',
        'company_name',
        'full_name',
        'emp_id',
        'emp_no',
        'position_id',
        'pos_code',
        'pos_name_en',
        'employment_status_code',
        'employment_status',
        'email',
        'company_id',
        'spv_parent',
        'spv_path',
        'start_date',
        'end_date',
        'photo',
        'address',
        'phone',
        'job_status',
        'email_verified',
        'worklocation_code',
        'worklocation_name',
        'cost_code',
        'costcenter_name',
        'dept_code',
        'dept_name_en',
        'org_unit',
        'employment_start_date',
        'employment_end_date',
        'customfield1',
        'customfield2',
        'customfield3',
        'customfield4',
        'customfield5',
        'customfield6',
        'customfield7',
        'customfield8',
        'customfield9',
        'customfield10',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'position_id' => 'integer',
        'company_id' => 'integer',
        'email_verified' => 'boolean',
        'start_date' => 'date',
        'end_date' => 'date',
        'employment_start_date' => 'date',
        'employment_end_date' => 'date',
    ];
}

