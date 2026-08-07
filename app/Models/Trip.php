<?php

namespace App\Models;

use App\Services\DeliveryCalendarAvailability;
use DB;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class Trip extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'trip_number',
        'driver_id',
        'vehicle_configuration_id',
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
                    'assigned_delivery_date' => $trip->scheduled_date,
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

    public function loadSummaryIsVisibleTo(?User $user): bool
    {
        if (! ($user?->can('view load summary') ?? false)) {
            return false;
        }

        $orders = $this->relationLoaded('orders')
            ? $this->orders
            : $this->orders()->get(['id', 'trip_id', 'is_printed']);
        $allTagsArePrinted = $orders
            ->every(fn (Order $order): bool => $order->is_printed);

        return $allTagsArePrinted
            || ($user?->can(Order::VIEW_UNPRINTED_PRODUCT_LINES_PERMISSION) ?? false);
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

    public function vehicleConfiguration(): BelongsTo
    {
        return $this->belongsTo(VehicleConfiguration::class);
    }

    public function dispatchConfirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dispatch_confirmed_by_user_id');
    }

    public function preTripInspections(): HasMany
    {
        return $this->hasMany(TripPreTripInspection::class);
    }

    public function isAssignedDriver(?User $user): bool
    {
        return $user?->employee?->getKey() !== null
            && (int) $this->driver_id === (int) $user->employee->getKey();
    }

    public function currentPreTripInspection(): ?TripPreTripInspection
    {
        if (! $this->driver_id) {
            return null;
        }

        $inspections = $this->relationLoaded('preTripInspections')
            ? $this->preTripInspections
            : $this->preTripInspections()->get();

        return $inspections
            ->where('driver_id', $this->driver_id)
            ->where('vehicle_configuration_id', $this->vehicle_configuration_id)
            ->where('report_type', TripPreTripInspection::TYPE_PRE_TRIP)
            ->sortByDesc(fn (TripPreTripInspection $inspection): string => sprintf(
                '%s-%010d',
                $inspection->completed_at?->format('Y-m-d H:i:s.u') ?? '',
                $inspection->getKey(),
            ))
            ->first();
    }

    public function currentDailyVehicleReport(): ?TripPreTripInspection
    {
        if (! $this->driver_id) {
            return null;
        }

        $inspections = $this->relationLoaded('preTripInspections')
            ? $this->preTripInspections
            : $this->preTripInspections()->get();

        $report = $inspections
            ->where('driver_id', $this->driver_id)
            ->where('report_type', TripPreTripInspection::TYPE_DAILY_REPORT)
            ->sortByDesc(fn (TripPreTripInspection $inspection): string => sprintf(
                '%s-%010d',
                $inspection->completed_at?->format('Y-m-d H:i:s.u') ?? '',
                $inspection->getKey(),
            ))
            ->first();

        if ($report) {
            return $report;
        }

        $preTrip = $this->currentPreTripInspection();

        if (! $preTrip) {
            return null;
        }

        return TripPreTripInspection::query()
            ->where('driver_id', $this->driver_id)
            ->where('report_type', TripPreTripInspection::TYPE_DAILY_REPORT)
            ->whereDate('scheduled_date', $this->scheduled_date?->toDateString())
            ->where('truck_asset_id', $preTrip->truck_asset_id)
            ->where('trailer_asset_id', $preTrip->trailer_asset_id)
            ->where('piggyback_asset_id', $preTrip->piggyback_asset_id)
            ->latest('completed_at')
            ->first();
    }

    public function reusablePreTripInspection(): ?TripPreTripInspection
    {
        if (! $this->driver_id || ! $this->vehicle_configuration_id || $this->currentPreTripInspection()) {
            return null;
        }

        $inspection = TripPreTripInspection::query()
            ->where('driver_id', $this->driver_id)
            ->where('vehicle_configuration_id', $this->vehicle_configuration_id)
            ->where('report_type', TripPreTripInspection::TYPE_PRE_TRIP)
            ->whereDate('inspection_date', now('America/Los_Angeles')->toDateString())
            ->where('safe_to_operate', true)
            ->whereDoesntHave('inspectionDefects', fn ($query) => $query->where('status', TripPreTripInspectionDefect::STATUS_OPEN))
            ->with(['truckAsset', 'trailerAsset', 'piggybackAsset', 'assets', 'inspectionDefects'])
            ->latest('completed_at')
            ->first();

        if (! $inspection) {
            return null;
        }

        $assets = collect([$inspection->truckAsset, $inspection->trailerAsset, $inspection->piggybackAsset])->filter();

        return $assets->every(fn (MaintenanceAsset $asset): bool => in_array($asset->status, ['operational', 'restricted'], true))
            ? $inspection
            : null;
    }

    public static function generateTripNumber()
    {
        return DB::transaction(function () {
            $lastTrip = static::withTrashed()
                ->lockForUpdate()
                ->orderBy('trip_number', 'desc')
                ->first();

            $newNumber = $lastTrip ? intval(substr($lastTrip->trip_number, 5)) + 1 : 1;

            return 'TRIP-'.str_pad($newNumber, 5, '0', STR_PAD_LEFT);
        });
    }
}
