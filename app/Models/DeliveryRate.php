<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeliveryRate extends Model
{
    use HasFactory;

    public const UNIT_VAULT = 'vault';
    public const UNIT_DELIVERY = 'delivery';

    protected $fillable = [
        'effective_date',
        'zone',
        'min_miles',
        'max_miles',
        'miles_label',
        'price',
        'price_unit',
    ];

    protected $casts = [
        'effective_date' => 'date',
        'min_miles' => 'decimal:2',
        'max_miles' => 'decimal:2',
        'price' => 'decimal:2',
    ];

    public function scopeCurrentForDate(Builder $query, mixed $date = null): Builder
    {
        $date ??= today();

        return $query->where('effective_date', function ($query) use ($date) {
            $query->selectRaw('MAX(effective_date)')
                ->from('delivery_rates')
                ->whereDate('effective_date', '<=', $date);
        });
    }

    public function getMilesRangeLabelAttribute(): string
    {
        return $this->miles_label;
    }

    public function getPriceLabelAttribute(): string
    {
        $label = '$' . number_format((float) $this->price, 2);

        return $this->isPerVault()
            ? "{$label} / vault"
            : $label;
    }

    public function getCalculationLabelAttribute(): string
    {
        return $this->isPerVault()
            ? 'Per vault'
            : 'Flat delivery rate';
    }

    public function isPerVault(): bool
    {
        return $this->price_unit === self::UNIT_VAULT;
    }

    public function isFlatDeliveryRate(): bool
    {
        return $this->price_unit === self::UNIT_DELIVERY;
    }

    public function calculateDeliveryCharge(int $vaultCount = 1): float
    {
        $quantity = $this->isPerVault()
            ? max(0, $vaultCount)
            : 1;

        return round((float) $this->price * $quantity, 2);
    }
}
