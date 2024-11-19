<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

class Trip extends Model
{
    protected $fillable = [
        'trip_number',
        'driver_id',
        'status',
        'scheduled_date',
        'start_time',
        'end_time',
        'notes',
    ];

    public function locations(): MorphToMany
    {
        return $this->morphToMany(Location::class, 'locationable')
            ->withPivot('type', 'sequence')
            ->withTimestamps();
    }

    public function startLocation()
    {
        return $this->locations()
            ->wherePivot('type', 'start_location')
            ->first();
    }

    public function deliveryLocations()
    {
        return $this->locations()
            ->wherePivot('type', 'delivery')
            ->orderBy('sequence');
    }

    public function driver()
    {
        return $this->belongsTo(Employee::class, 'driver_id');
    }
}
