<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class OrderProduct extends Pivot
{
    use LogsActivity;

    protected $table = 'order_product';

    public $incrementing = true;

    protected $fillable = [
        'order_id',
        'product_id',
        'custom_description',
        'is_custom_product',
        'quantity',
        'quantity_delivered',
        'delivery_notes',
        'price',
        'notes',
        'location',
        'fill_load'
    ];

    protected $casts = [
        'fill_load' => 'integer',
        'is_custom_product' => 'boolean',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function getDisplaySkuAttribute(): string
    {
        return $this->is_custom_product ? 'CUSTOM' : $this->product->sku;
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->is_custom_product ? ($this->custom_description ?? 'Custom Product') : $this->product->name;
    }

    public function getDisplayDescriptionAttribute(): ?string
    {
        return $this->is_custom_product ? $this->custom_description : $this->product->description;
    }

       public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnlyDirty()
            ->logAll()
            ->useLogName('order_product');
    }
}
