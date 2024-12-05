<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Order extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'order_number',
        'driver_id',
        'customer_id',
        'location_id',
        'status',
        'order_date',
        'requested_delivery_date',
        'assigned_delivery_date',
        'delivery_time',
        'arrived_at',
        'delivered_at',
        'service_date',
        'special_instructions',
        'trip_id',
        'stop_number',
        'delivery_notes',
        'signature_path',
    ];

    protected $casts = [
        'order_date' => 'date',
        'requested_delivery_date' => 'date',
        'assigned_delivery_date' => 'date',
        'service_date' => 'datetime',
        'is_active' => 'boolean',
        'arrived_at' => 'datetime',
        'delivered_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            // Generate UUID
            $model->uuid = (string) Str::uuid();

            // Get the highest order number including soft-deleted records
            $latestOrder = static::withTrashed()
                ->orderBy('id', 'desc')  // Ensure we get the absolute latest
                ->first();

            $lastNumber = 0;
            if ($latestOrder && $latestOrder->order_number) {
                preg_match('/ORD-(\d+)/', $latestOrder->order_number, $matches);
                $lastNumber = isset($matches[1]) ? (int)$matches[1] : 0;
            }

            $nextNumber = $lastNumber + 1;

            // Generate order number with padding
            $model->order_number = sprintf('ORD-%05d', $nextNumber);
        });

        static::created(function ($order) {
            if (request()->has('data.orderProducts')) {
                foreach (request()->input('data.orderProducts') as $product) {
                    $order->orderProducts()->create([
                        'product_id' => $product['product_id'],
                        'fill_load' => (bool) ($product['fill_load'] ?? false),
                        'quantity' => $product['quantity'] ?? null,
                        'price' => $product['price'] ?? 0,
                        'location' => $product['location'] ?? null,
                        'notes' => $product['notes'] ?? null,
                    ]);
                }
            }
        });
    }


    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function orderProducts(): HasMany
    {
        return $this->hasMany(OrderProduct::class);
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class)
            ->using(OrderProduct::class)
            ->withPivot('quantity', 'price')
            ->withTimestamps();
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'driver_id');
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'delivered' => '#D9EDBF',   // Soft green from status-delivered
            'cancelled' => '#FFB996',   // Soft coral from status-cancelled
            'pending' => '#FFCF81',     // Soft orange from status-pending
            'confirmed' => '#FDFFAB',   // Soft yellow from status-confirmed
            default => '#6B7280',       // Default gray
        };
    }
}
