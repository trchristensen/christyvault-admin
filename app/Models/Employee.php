<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    use HasFactory;
    protected $fillable = [
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


    // ... other properties and methods ...

    public function driver()
    {
        return $this->hasOne(Driver::class);
    }

    public function isDriver()
    {
        return $this->position === 'driver' && $this->driver()->exists();
    }
}
