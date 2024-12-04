<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;


class Trip extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'trip_number',
        'driver_id',
        'status',
        'scheduled_date',
        'start_time',
        'end_time',
        'notes',
        'uuid',
    ];

    protected $casts = [
        'scheduled_date' => 'date',
        'start_time' => 'datetime',
        'end_time' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($trip) {
            $trip->uuid = $trip->uuid ?? Str::uuid();
            $trip->trip_number = $trip->trip_number ?? static::generateTripNumber();
        });
        static::updating(function ($trip) {
            $trip->uuid = (string) str()->uuid();
        });

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

    public static function generateTripNumber()
    {
        return \DB::transaction(function () {
            $lastTrip = static::withTrashed()
                ->lockForUpdate()
                ->orderBy('trip_number', 'desc')
                ->first();
            
            $newNumber = $lastTrip ? intval(substr($lastTrip->trip_number, 5)) + 1 : 1;
            return 'TRIP-' . str_pad($newNumber, 5, '0', STR_PAD_LEFT);
        });
    }
}
