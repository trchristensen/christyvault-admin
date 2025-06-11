<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Driver extends Model
{
    protected $fillable = [
        'employee_id',
        'license_number',
        'license_expiration',
        // 'vehicle_type',
        'notes',
        'sms_consent_given',
        'sms_consent_at',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
// 