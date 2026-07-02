<?php

namespace App\Services;

use App\Models\DeliveryRate;

class DeliveryRateService
{
    public function rateForMiles(float|int|null $miles, mixed $date = null): ?DeliveryRate
    {
        if ($miles === null) {
            return null;
        }

        return DeliveryRate::query()
            ->currentForDate($date)
            ->where('min_miles', '<=', $miles)
            ->where(function ($query) use ($miles) {
                $query->whereNull('max_miles')
                    ->orWhere('max_miles', '>=', $miles);
            })
            ->orderBy('min_miles')
            ->first();
    }

    public function quoteForMiles(float|int|null $miles, int $vaultCount = 1, mixed $date = null): ?array
    {
        $rate = $this->rateForMiles($miles, $date);

        if (! $rate) {
            return null;
        }

        return [
            'rate' => $rate,
            'vault_count' => $vaultCount,
            'amount' => $rate->calculateDeliveryCharge($vaultCount),
            'calculation' => $rate->calculation_label,
        ];
    }
}
