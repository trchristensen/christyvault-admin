<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Facades\DB;

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

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            // Force the fill_load to be a proper PostgreSQL boolean
            if (isset($model->attributes['fill_load'])) {
                $model->attributes['fill_load'] = DB::raw($model->attributes['fill_load'] ? 'TRUE' : 'FALSE');
            }
        });

        static::updating(function ($model) {
            if (isset($model->attributes['fill_load'])) {
                $model->attributes['fill_load'] = DB::raw($model->attributes['fill_load'] ? 'TRUE' : 'FALSE');
            }
        });
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
