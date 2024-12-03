<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employee extends Model
{
    use HasFactory, SoftDeletes;
    protected $fillable = [
        'user_id',
        'name',
        'email',
        'address',
        'phone',
        'position',
        'is_active',
        'christy_location',
        'hire_date',
        'birth_date',
        // driver fields
        'notes',
        'license_number',
        'license_expiration',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];


    // ... other properties and methods ...

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function leaveRequests()
    {
        return $this->hasMany(LeaveRequest::class);
    }

    public function driver()
    {
        return $this->hasOne(Driver::class);
    }

    public function isDriver()
    {
        return $this->position === 'driver' && $this->driver()->exists();
    }

    public function christyVaultLocation()
    {
        return Location::where('location_type', 'christy_vault')
            ->where('name', 'like', '%' . $this->christy_location . '%');
    }
}
