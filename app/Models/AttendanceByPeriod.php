<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttendanceByPeriod extends Model
{
    protected $table = 'attendance_by_period';
    public $timestamps = true;

    protected $fillable = [
        'attend_id',
        'emp_id',
        'shiftdaily_code',
        'company_id',
        'shiftstarttime',
        'shiftendtime',
        'attend_code',
        'starttime',
        'endtime',
        'actual_in',
        'actual_out',
        'daytype',
        'ip_starttime',
        'ip_endtime',
        'remark',
        'default_shift',
        'total_ot',
        'total_otindex',
        'overtime_code',
        'flexibleshift',
        'auto_ovt',
        'actualworkmnt',
        'actual_lti',
        'actual_eao',
        'geolocation',
        'geoloc_start',
        'geoloc_end',
        'photo_start',
        'photo_end',
        'emp_no',
        'spv_no',
        'spv_id',
        'pos_name_en',
        'pos_name_id',
        'pos_name_my',
        'pos_name_th',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'shiftstarttime' => 'datetime',
        'shiftendtime' => 'datetime',
        'starttime' => 'datetime',
        'endtime' => 'datetime',
        'actual_in' => 'integer',
        'actual_out' => 'integer',
        'total_ot' => 'decimal:2',
        'total_otindex' => 'decimal:2',
        'auto_ovt' => 'boolean',
        'actualworkmnt' => 'integer',
        'actual_lti' => 'integer',
        'actual_eao' => 'integer',
    ];
}

