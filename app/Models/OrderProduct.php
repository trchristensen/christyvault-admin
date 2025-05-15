<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class OrderProduct extends Pivot
{
    protected $table = 'order_product';

    public $incrementing = true;

    protected $fillable = [
        'order_id',
        'product_id',
        'custom_sku',
        'custom_name',
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
        return $this->is_custom_product ? $this->custom_sku : $this->product->sku;
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->is_custom_product ? $this->custom_name : $this->product->name;
    }

    public function getDisplayDescriptionAttribute(): ?string
    {
        return $this->is_custom_product ? $this->custom_description : $this->product->description;
    }
}
