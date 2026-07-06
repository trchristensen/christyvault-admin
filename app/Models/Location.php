<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Services\DeliveryRateService;
use Propaganistas\LaravelPhone\PhoneNumber;

class Location extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'address_line1',
        'address_line2',
        'city',
        'state',
        'postal_code',
        'latitude',
        'longitude',
        'geocoding_provider',
        'geocoding_matched_address',
        'geocoded_at',
        'geocoding_failed_at',
        'geocoding_failure_reason',
        'plant_drive_distance_origin_location_id',
        'plant_drive_distance_miles',
        'plant_drive_duration_minutes',
        'plant_drive_distance_provider',
        'plant_drive_distance_calculated_at',
        'location_type',
        'default_plant_location',
        'notes',
        'preferred_delivery_contact_id',
        'phone',
        'email',
        'last_order_at',
        'average_order_frequency_days',
        'common_order_items',
        'total_orders',
        'average_order_value',
    ];

    protected $casts = [
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'geocoded_at' => 'datetime',
        'geocoding_failed_at' => 'datetime',
        'plant_drive_distance_miles' => 'decimal:2',
        'plant_drive_distance_calculated_at' => 'datetime',
        'last_order_at' => 'datetime',
        'common_order_items' => 'array',
        'average_order_value' => 'decimal:2',
        'default_plant_location' => \App\Enums\PlantLocation::class,
    ];

    public function getCoordinatesAttribute(): ?string
    {
        if ($this->latitude && $this->longitude) {
            return "{$this->latitude}, {$this->longitude}";
        }
        return null;
    }

    public function hasCoordinates(): bool
    {
        return !is_null($this->latitude) && !is_null($this->longitude);
    }

    public function hasAddressForGeocoding(): bool
    {
        return filled($this->address_line1)
            && filled($this->city)
            && filled($this->state);
    }

    public function addressFieldsChanged(): bool
    {
        return $this->isDirty([
            'address_line1',
            'address_line2',
            'city',
            'state',
            'postal_code',
        ]);
    }

    public function trips(): MorphToMany
    {
        return $this->morphedByMany(Trip::class, 'locationable')
            ->withPivot('type', 'sequence')
            ->withTimestamps();
    }

    public function customers(): MorphToMany
    {
        return $this->morphedByMany(Customer::class, 'locationable')
            ->withPivot('type', 'sequence')
            ->withTimestamps();
    }

    // Add this to your existing Location model
    public function contacts(): BelongsToMany
    {
        return $this->belongsToMany(Contact::class, 'contact_location')
            ->withPivot('is_primary')
            ->withTimestamps();
    }

    public function salesVisits(): HasMany
    {
        return $this->hasMany(SalesVisit::class);
    }

    public function preferredDeliveryContact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'preferred_delivery_contact_id');
    }

    public function plantDriveDistanceOrigin(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'plant_drive_distance_origin_location_id');
    }

    public function clearPlantDriveDistance(): void
    {
        $this->plant_drive_distance_origin_location_id = null;
        $this->plant_drive_distance_miles = null;
        $this->plant_drive_duration_minutes = null;
        $this->plant_drive_distance_provider = null;
        $this->plant_drive_distance_calculated_at = null;
    }

    public function getPlantDriveDistanceSummaryAttribute(): ?string
    {
        if ($this->plant_drive_distance_miles === null || $this->plant_drive_duration_minutes === null) {
            return null;
        }

        $distance = number_format((float) $this->plant_drive_distance_miles, 1);
        $originName = $this->plantDriveDistanceOrigin?->name;

        return collect([
            "{$distance} mi",
            "{$this->plant_drive_duration_minutes} min",
            $originName ? "from {$originName}" : null,
        ])->filter()->join(' • ');
    }

    public function getCurrentDeliveryRateAttribute(): ?DeliveryRate
    {
        return app(DeliveryRateService::class)->rateForMiles(
            $this->plant_drive_distance_miles !== null ? (float) $this->plant_drive_distance_miles : null
        );
    }

    public function getCurrentDeliveryRateSummaryAttribute(): ?string
    {
        $rate = $this->current_delivery_rate;

        if (! $rate) {
            return null;
        }

        return "Zone {$rate->zone} • {$rate->price_label}";
    }

    public function getFullAddressAttribute(): string
    {
        if (!$this->address_line1) {
            return '';
        }

        return collect([
            $this->address_line1,
            $this->address_line2,
            $this->city,
            $this->state,
            $this->postal_code,
        ])->filter()->join(', ');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }


    public function getFormattedPreferredPhoneAttribute(): string
    {
        if ($this->preferredDeliveryContact) {
            $contact = $this->preferredDeliveryContact;

            $main = $this->formatPhone($contact->phone);
            $mobile = $this->formatPhone($contact->mobile_phone);

            $parts = ["Contact: {$contact->name}"];

            if ($main) {
                $part = "- {$main}";
                if ($contact->phone_extension) {
                    $part .= " x{$contact->phone_extension}";
                }
                $parts[] = $part;
            }

            if ($mobile) {
                $parts[] = "• M: {$mobile}";
            }

            return implode(' ', $parts);
        }

        // Fallback to location's own phone
        if ($this->phone) {
            $phone = $this->formatPhone($this->phone);
            if ($this->phone_extension) {
                $phone .= " x{$this->phone_extension}";
            }
            return $phone;
        }

        return '';
    }

    private function formatPhone(?string $number): ?string
    {
        if (!$number) {
            return null;
        }

        try {
            return PhoneNumber::make($number, 'US')
                ->format('(###) ###-####');
        } catch (\Exception) {
            return $number; // fallback to raw
        }
    }



    // junk, below this.

    public function updateOrderAnalytics(): void
    {
        $orders = $this->orders()
            ->with('orderProducts.product')
            ->where('status', '!=', OrderStatus::CANCELLED->value)
            ->whereNotNull('order_date')
            ->orderBy('order_date', 'desc')
            ->get();

        if ($orders->isEmpty()) {
            $this->forceFill([
                'last_order_at' => null,
                'average_order_frequency_days' => null,
                'common_order_items' => null,
                'total_orders' => 0,
                'average_order_value' => null,
            ])->saveQuietly();

            return;
        }

        $averageOrderValue = $orders->avg(function ($order) {
            return $order->orderProducts->sum(function ($product) {
                return (float) ($product->quantity ?? 0) * (float) ($product->price ?? 0);
            });
        });

        $averageOrderFrequencyDays = null;

        if ($orders->count() > 1) {
            $orderDates = $orders->pluck('order_date')->filter()->sort()->values();
            $totalDays = 0;
            $count = 0;

            for ($i = 1; $i < $orderDates->count(); $i++) {
                $days = $orderDates[$i]->diffInDays($orderDates[$i - 1], true);
                if ($days > 0) { // Only count if there's a gap
                    $totalDays += $days;
                    $count++;
                }
            }

            $averageOrderFrequencyDays = $count > 0 ? round($totalDays / $count) : null;
        }

        $productCounts = [];

        foreach ($orders as $order) {
            foreach ($order->orderProducts as $product) {
                if ($product->is_custom_product) {
                    continue;
                }

                $sku = $product->product->sku ?? null;

                if (! $sku) {
                    continue;
                }

                $productId = $product->product_id;

                if (!isset($productCounts[$productId])) {
                    $productCounts[$productId] = [
                        'count' => 0,
                        'sku' => $sku,
                        'last_ordered' => $order->order_date,
                    ];
                }
                $productCounts[$productId]['count']++;
            }
        }

        uasort($productCounts, function ($a, $b) {
            return $b['count'] <=> $a['count'];
        });

        $this->forceFill([
            'last_order_at' => $orders->first()->order_date,
            'average_order_frequency_days' => $averageOrderFrequencyDays,
            'common_order_items' => array_slice($productCounts, 0, 5, true),
            'total_orders' => $orders->count(),
            'average_order_value' => $averageOrderValue,
        ])->saveQuietly();
    }

    public function getOrderStatusAttribute(): string
    {
        if (!$this->last_order_at) {
            return 'No Orders';
        }

        $daysSinceLastOrder = now()->diffInDays($this->last_order_at, true);

        if (!$this->average_order_frequency_days) {
            return 'New Customer';
        }

        if ($daysSinceLastOrder > ($this->average_order_frequency_days * 1.5)) {
            return 'Overdue';
        } elseif ($daysSinceLastOrder > $this->average_order_frequency_days) {
            return 'Due Soon';
        } else {
            return 'Recently Ordered';
        }
    }

    public function getOrderStatusColorAttribute(): string
    {
        return match ($this->order_status) {
            'No Orders' => 'gray',
            'New Customer' => 'blue',
            'Overdue' => 'red',
            'Due Soon' => 'yellow',
            'Recently Ordered' => 'green',
            default => 'gray',
        };
    }
}
