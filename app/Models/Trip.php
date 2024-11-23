<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

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

    protected $casts = [
        'scheduled_date' => 'date',
    ];

    protected static function boot()
    {
        parent::boot();

        // When trip is updated
        static::updated(function ($trip) {
            if ($trip->wasChanged('scheduled_date')) {
                $trip->orders()->update([
                    'assigned_delivery_date' => $trip->scheduled_date
                ]);
            }
        });

        // When trip is created
        static::created(function ($trip) {
            if ($trip->scheduled_date) {
                $trip->orders()->update([
                    'assigned_delivery_date' => $trip->scheduled_date
                ]);
            }
        });
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }


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
        return $this->belongsTo(Employee::class, 'driver_id', 'id');
    }
}
