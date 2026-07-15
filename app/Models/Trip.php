<?php

namespace App\Models;

use App\Services\DeliveryCalendarAvailability;
use DB;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
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
        'dispatch_confirmed_at',
        'dispatch_confirmed_by_user_id',
    ];

    protected $casts = [
        'scheduled_date' => 'date',
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'dispatch_confirmed_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($trip) {
            if ($trip->scheduled_date) {
                app(DeliveryCalendarAvailability::class)->validateDate(
                    $trip->scheduled_date,
                    'scheduled_date'
                );
            }

            $trip->uuid = $trip->uuid ?? Str::uuid();
            $trip->trip_number = $trip->trip_number ?? static::generateTripNumber();
        });
        static::updating(function ($trip) {
            if ($trip->isDirty('scheduled_date') && $trip->scheduled_date) {
                app(DeliveryCalendarAvailability::class)->validateDate(
                    $trip->scheduled_date,
                    'scheduled_date'
                );

                $trip->dispatch_confirmed_at = null;
                $trip->dispatch_confirmed_by_user_id = null;
            }

        });

        // When trip is updated
        static::updated(function ($trip) {
            $orderUpdates = [];

            if ($trip->wasChanged('scheduled_date')) {
                $orderUpdates['assigned_delivery_date'] = $trip->scheduled_date;
            }

            if ($trip->wasChanged('driver_id')) {
                $orderUpdates['driver_id'] = $trip->driver_id;
            }

            if ($orderUpdates !== []) {
                $trip->orders()->update($orderUpdates);
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

    public function stops(): HasMany
    {
        return $this->hasMany(TripStop::class)
            ->whereNull('removed_at')
            ->orderBy('sequence');
    }

    public function stopHistory(): HasMany
    {
        return $this->hasMany(TripStop::class)->orderBy('id');
    }

    /**
     * Resolve the canonical stop records first, with a legacy-order fallback so
     * deployments remain functional before the production backfill is applied.
     */
    public function orderedDeliveryOrders(): Collection
    {
        $stops = $this->relationLoaded('stops')
            ? $this->stops
            : $this->stops()->with('order')->get();

        if ($stops->isNotEmpty()) {
            return $stops
                ->sortBy('sequence')
                ->pluck('order')
                ->filter()
                ->values();
        }

        $orders = $this->relationLoaded('orders')
            ? $this->orders
            : $this->orders()->get();

        return $orders->sortBy('stop_number')->values();
    }

    public function deliveryStopCount(): int
    {
        return $this->orderedDeliveryOrders()->count();
    }

    public function isStopOrderConfirmed(): bool
    {
        return $this->deliveryStopCount() <= 1 || $this->dispatch_confirmed_at !== null;
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

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'driver_id', 'id');
    }

    public function dispatchConfirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dispatch_confirmed_by_user_id');
    }

    public static function generateTripNumber()
    {
        return DB::transaction(function () {
            $lastTrip = static::withTrashed()
                ->lockForUpdate()
                ->orderBy('trip_number', 'desc')
                ->first();
            
            $newNumber = $lastTrip ? intval(substr($lastTrip->trip_number, 5)) + 1 : 1;
            return 'TRIP-' . str_pad($newNumber, 5, '0', STR_PAD_LEFT);
        });
    }
}
