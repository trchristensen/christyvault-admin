<?php

namespace App\Models;

use App\Enums\UnitOfMeasure;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Product extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'name',
        'sku',
        'unit',
        'description',
        'price',
        'stock',
        'weight_lbs',
        'loading_profile_id',
        'is_active',
        'specifications',
        'featured_image',
        'product_type',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'weight_lbs' => 'decimal:2',
        'is_active' => 'boolean',
        'specifications' => 'array',
        'unit' => UnitOfMeasure::class,
    ];

    public function scopeActive($query)
    {
        return $query->whereRaw('is_active IS TRUE');
    }

    public function orders(): BelongsToMany
    {
        return $this->belongsToMany(Order::class)
            ->using(OrderProduct::class)
            ->withPivot('quantity', 'price')
            ->withTimestamps();
    }

    public function loadingProfile(): BelongsTo
    {
        return $this->belongsTo(LoadingProfile::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnlyDirty()
            ->logAll()
            ->useLogName('product');
    }
}
