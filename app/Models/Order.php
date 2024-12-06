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
use App\Enums\OrderStatus;
use Filament\Support\Colors\Color;


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

    public function getStatusColorAttribute(): array
    {
        $colors = [
            'Yellow' => [
                50 => 'rgb(254 249 195)',  // yellow-50
                500 => 'rgb(234 179 8)',   // yellow-500
                900 => 'rgb(113 63 18)',   // yellow-900
            ],
            'Blue' => [
                50 => 'rgb(239 246 255)',  // blue-50
                500 => 'rgb(59 130 246)',  // blue-500
                900 => 'rgb(30 58 138)',   // blue-900
            ],
            'Purple' => [
                50 => 'rgb(250 245 255)',  // purple-50
                500 => 'rgb(168 85 247)',  // purple-500
                900 => 'rgb(88 28 135)',   // purple-900
            ],
            // ... add other colors as needed
        ];

        $getColor = function(string $color, int $shade) use ($colors) {
            return $colors[$color][$shade] ?? '#000000';
        };

        return match ($this->status) {
            OrderStatus::PENDING->value => [
                'background' => $getColor('Yellow', 50),
                'text' => $getColor('Yellow', 900),
                'border' => $getColor('Yellow', 500),
            ],
            OrderStatus::CONFIRMED->value => [
                'background' => $getColor('Blue', 50),
                'text' => $getColor('Blue', 900),
                'border' => $getColor('Blue', 500),
            ],
            OrderStatus::IN_PRODUCTION->value => [
                'background' => $getColor('Purple', 50),
                'text' => $getColor('Purple', 900),
                'border' => $getColor('Purple', 500),
            ],
            OrderStatus::READY_FOR_DELIVERY->value => [
                'background' => $getColor('Teal', 50),
                'text' => $getColor('Teal', 900),
                'border' => $getColor('Teal', 500),
            ],
            OrderStatus::OUT_FOR_DELIVERY->value => [
                'background' => $getColor('Orange', 50),
                'text' => $getColor('Orange', 900),
                'border' => $getColor('Orange', 500),
            ],
            OrderStatus::DELIVERED->value => [
                'background' => $getColor('Green', 100),
                'text' => $getColor('Green', 900),
                'border' => $getColor('Green', 600),
            ],
            OrderStatus::CANCELLED->value => [
                'background' => $getColor('Red', 50),
                'text' => $getColor('Red', 900),
                'border' => $getColor('Red', 500),
            ],
            OrderStatus::INVOICED->value => [
                'background' => $getColor('Indigo', 50),
                'text' => $getColor('Indigo', 900),
                'border' => $getColor('Indigo', 500),
            ],
            OrderStatus::COMPLETED->value => [
                'background' => $getColor('Green', 200),
                'text' => $getColor('Green', 900),
                'border' => $getColor('Green', 700),
            ],
            default => [
                'background' => $getColor('Gray', 50),
                'text' => $getColor('Gray', 900),
                'border' => $getColor('Gray', 500),
            ],
        };
    }
}
