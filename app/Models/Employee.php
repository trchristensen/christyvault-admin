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
        'is_active',
        'christy_location',
        'hire_date',
        'birth_date',
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

    public function positions()
    {
        return $this->belongsToMany(Position::class)->withTimestamps();
    }

    public function isDriver()
    {
        return $this->positions()->where('name', 'driver')->exists() && $this->driver()->exists();
    }

    public function hasPosition(string $position): bool
    {
        return $this->positions()->where('name', $position)->exists();
    }

    public function christyVaultLocation()
    {
        return Location::where('location_type', 'christy_vault')
            ->where('name', 'like', '%' . $this->christy_location . '%');
    }
}
