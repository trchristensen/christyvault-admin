<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
        'location_type',
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
        'last_order_at' => 'datetime',
        'common_order_items' => 'array',
        'average_order_value' => 'decimal:2',
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
            ->where('status', '!=', 'cancelled')
            ->orderBy('order_date', 'desc')
            ->get();

        if ($orders->isEmpty()) {
            return;
        }

        // Update last order date
        $this->last_order_at = $orders->first()->order_date;

        // Update total orders
        $this->total_orders = $orders->count();

        // Calculate average order value
        $this->average_order_value = $orders->avg(function ($order) {
            return $order->orderProducts->sum(function ($product) {
                return $product->quantity * $product->price;
            });
        });

        // Calculate average order frequency
        if ($orders->count() > 1) {
            $orderDates = $orders->pluck('order_date')->sort();
            $totalDays = 0;
            $count = 0;

            for ($i = 1; $i < $orderDates->count(); $i++) {
                $days = $orderDates[$i]->diffInDays($orderDates[$i - 1]);
                if ($days > 0) { // Only count if there's a gap
                    $totalDays += $days;
                    $count++;
                }
            }

            $this->average_order_frequency_days = $count > 0 ? round($totalDays / $count) : null;
        }

        // Calculate common order items
        $productCounts = [];
        foreach ($orders as $order) {
            foreach ($order->orderProducts as $product) {
                $sku = $product->product->sku ?? 'Unknown SKU';
                if ($sku === 'Unknown SKU') {
                    continue; // Skip unknown SKUs
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

        // Sort by count and take top 5
        uasort($productCounts, function ($a, $b) {
            return $b['count'] <=> $a['count'];
        });

        $this->common_order_items = array_slice($productCounts, 0, 5, true);

        $this->save();
    }

    public function getOrderStatusAttribute(): string
    {
        if (!$this->last_order_at) {
            return 'No Orders';
        }

        $daysSinceLastOrder = now()->diffInDays($this->last_order_at);

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
