<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Trip extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'trip_number',
        'driver_id',
        'status',
        'scheduled_date',
        'start_time',
        'end_time',
        'notes'
    ];

    protected $casts = [
        'scheduled_date' => 'date',
        'start_time' => 'datetime',
        'end_time' => 'datetime'
    ];

    // Auto-generate trip_number when creating
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($trip) {
            $trip->trip_number = 'TRIP-' . date('Y') . '-' .
                str_pad((static::max('id') ?? 0) + 1, 5, '0', STR_PAD_LEFT);
        });
    }

    public function driver()
    {
        return $this->belongsTo(Employee::class, 'driver_id');
    }

    public function orders()
    {
        return $this->hasMany(Order::class)->orderBy('stop_number');
    }
}
