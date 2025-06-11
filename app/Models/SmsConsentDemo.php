<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SmsConsentDemo extends Model
{
    protected $fillable = [
        'employee_name',
        'employee_id',
        'phone_number',
        'work_email',
        'ip_address',
        'user_agent',
    ];
}
