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
        'special_instructions',
        'trip_id',           // Add these
        'stop_number',       // three
        'delivery_notes',    // fields
    ];

    protected $casts = [
        'order_date' => 'date',
        'requested_delivery_date' => 'date',
        'assigned_delivery_date' => 'date',
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
            'cancelled' => '#EF4444',   // red
            'pending' => '#F59E0B',     // yellow
            'confirmed' => '#3B82F6',   // blue
            'in_production' => '#8B5CF6', // purple
            'ready_for_delivery' => '#10B981', // green
            'out_for_delivery' => '#F59E0B', // yellow
            'delivered' => '#10B981',   // green
            default => '#6B7280',       // gray
        };
    }
}
