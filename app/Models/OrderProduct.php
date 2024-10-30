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
        'quantity',
        'price',
        'notes',
        'location',
        'fill_load'
    ];

    protected $casts = [
        'fill_load' => 'boolean',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
