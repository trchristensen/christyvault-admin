<?php

namespace App\Services\LoadPlanning;

use App\Models\LoadingProfile;
use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\Trip;
use App\Models\VehicleConfiguration;
use Illuminate\Support\Collection;

class LoadDemandService
{
    public function forTrip(Trip $trip, array $fillQuantityOverrides = []): LoadDemandResult
    {
        if ($trip->exists) {
            $trip->loadMissing([
                'vehicleConfiguration',
                'stops.order.location',
                'stops.order.orderProducts.product.loadingProfile.requiredRackType',
                'stops.order.orderProducts.product.loadingProfile.allowedRackTypes',
                'orders.location',
                'orders.orderProducts.product.loadingProfile.requiredRackType',
                'orders.orderProducts.product.loadingProfile.allowedRackTypes',
            ]);
        }

        $orders = $trip->orderedDeliveryOrders();
        $stopSequenceByOrder = $trip->relationLoaded('stops')
            ? $trip->stops->pluck('sequence', 'order_id')
            : collect();
        $result = $this->build(
            $orders,
            $trip->vehicleConfiguration,
            fn (Order $order, int $index): int => (int) (
                $stopSequenceByOrder->get($order->getKey())
                ?? $order->stop_number
                ?? ($index + 1)
            ),
            $fillQuantityOverrides,
        );

        if (! $trip->vehicleConfiguration) {
            $warnings = $result->warnings;
            array_unshift($warnings, [
                'code' => 'missing_vehicle_configuration',
                'message' => 'Select a vehicle configuration before generating rack placement.',
                'blocking' => true,
            ]);

            return new LoadDemandResult(
                $result->summary,
                $result->stops,
                $warnings,
                null,
            );
        }

        return $result;
    }

    public function forOrder(
        Order $order,
        ?VehicleConfiguration $vehicleConfiguration = null,
        array $fillQuantityOverrides = [],
    ): LoadDemandResult {
        if ($order->exists) {
            $order->loadMissing([
                'location',
                'orderProducts.product.loadingProfile.requiredRackType',
                'orderProducts.product.loadingProfile.allowedRackTypes',
            ]);
        }

        return $this->build(
            collect([$order]),
            $vehicleConfiguration,
            fillQuantityOverrides: $fillQuantityOverrides,
        );
    }

    private function build(
        Collection $orders,
        ?VehicleConfiguration $vehicleConfiguration,
        ?callable $sequenceResolver = null,
        array $fillQuantityOverrides = [],
    ): LoadDemandResult {
        $summary = [
            'product_units' => 0,
            'standard_box_units' => 0,
            'oversized_rack_spots' => 0,
            'pallets' => 0,
            'known_weight_lbs' => 0.0,
            'unknown_weight_items' => 0,
        ];
        $warnings = [];
        $stops = [];

        foreach ($orders->values() as $index => $order) {
            $sequence = $sequenceResolver
                ? $sequenceResolver($order, $index)
                : ($index + 1);
            $stop = $this->buildStop($order, $sequence, $fillQuantityOverrides);

            foreach ($summary as $key => $value) {
                $summary[$key] += $stop['summary'][$key];
            }

            $warnings = [...$warnings, ...$stop['warnings']];
            unset($stop['warnings']);
            $stops[] = $stop;
        }

        usort($stops, fn (array $left, array $right): int => $left['sequence'] <=> $right['sequence']);
        $summary['known_weight_lbs'] = round($summary['known_weight_lbs'], 2);
        $maximumProductWeight = $vehicleConfiguration?->max_product_weight_lbs === null
            ? null
            : (float) $vehicleConfiguration->max_product_weight_lbs;
        $overweightBy = $maximumProductWeight === null
            ? 0.0
            : max(0, $summary['known_weight_lbs'] - $maximumProductWeight);
        $summary['maximum_product_weight_lbs'] = $maximumProductWeight;
        $summary['remaining_product_weight_lbs'] = $maximumProductWeight === null
            ? null
            : round(max(0, $maximumProductWeight - $summary['known_weight_lbs']), 2);
        $summary['overweight_by_lbs'] = round($overweightBy, 2);
        $summary['is_overweight'] = $overweightBy > 0;

        if ($summary['is_overweight']) {
            array_unshift($warnings, [
                'code' => 'weight_limit_exceeded',
                'message' => sprintf(
                    'Product cargo is %s lb over the %s lb vehicle limit.',
                    number_format($summary['overweight_by_lbs'], 0),
                    number_format($maximumProductWeight, 0),
                ),
                'blocking' => true,
            ]);
        }

        foreach ($this->fullLoadProfileTotals($stops) as $profileTotal) {
            if ($profileTotal['quantity'] <= $profileTotal['full_load_units']) {
                continue;
            }

            $warnings[] = [
                'code' => 'physical_full_load_exceeded',
                'message' => sprintf(
                    '%s has %d units; its confirmed physical full-load quantity is %d.',
                    $profileTotal['profile_name'],
                    $profileTotal['quantity'],
                    $profileTotal['full_load_units'],
                ),
                'blocking' => true,
                'loading_profile' => $profileTotal['profile_code'],
            ];
        }

        return new LoadDemandResult(
            $summary,
            $stops,
            $warnings,
            $vehicleConfiguration ? [
                'id' => $vehicleConfiguration->getKey(),
                'name' => $vehicleConfiguration->name,
                'type' => $vehicleConfiguration->configuration_type,
                'rack_spot_count' => $vehicleConfiguration->rack_spot_count,
                'flatbed_pallet_capacity' => $vehicleConfiguration->flatbed_pallet_capacity,
                'max_product_weight_lbs' => $maximumProductWeight,
                'piggyback_forklift_onboard' => $vehicleConfiguration->piggyback_forklift_onboard,
            ] : null,
        );
    }

    private function buildStop(Order $order, int $sequence, array $fillQuantityOverrides): array
    {
        $summary = [
            'product_units' => 0,
            'standard_box_units' => 0,
            'oversized_rack_spots' => 0,
            'pallets' => 0,
            'known_weight_lbs' => 0.0,
            'unknown_weight_items' => 0,
        ];
        $warnings = [];
        $items = [];
        $palletBuckets = [];

        foreach ($order->orderProducts as $orderProduct) {
            $item = $this->buildItem(
                $order,
                $orderProduct,
                $summary,
                $warnings,
                $palletBuckets,
                $fillQuantityOverrides,
            );
            $items[] = $item;
        }

        foreach ($palletBuckets as $palletEquivalent) {
            $summary['pallets'] += (int) ceil($palletEquivalent - 0.000001);
        }

        $summary['known_weight_lbs'] = round($summary['known_weight_lbs'], 2);

        return [
            'sequence' => $sequence,
            'unload_position' => $sequence === 1 ? 'rear_first' : 'after_prior_stops',
            'order_id' => $order->getKey(),
            'order_number' => $order->order_number,
            'location_name' => $order->location?->name,
            'summary' => $summary,
            'items' => $items,
            'warnings' => $warnings,
        ];
    }

    private function buildItem(
        Order $order,
        OrderProduct $orderProduct,
        array &$summary,
        array &$warnings,
        array &$palletBuckets,
        array $fillQuantityOverrides,
    ): array {
        $product = $orderProduct->product;
        $isFillLoad = (bool) $orderProduct->fill_load;
        $overrideKey = $orderProduct->getKey();
        $hasFillOverride = $isFillLoad
            && $overrideKey !== null
            && array_key_exists($overrideKey, $fillQuantityOverrides);
        $quantity = $isFillLoad
            ? ($hasFillOverride
                ? max(0, (int) $fillQuantityOverrides[$overrideKey])
                : ($orderProduct->planned_fill_quantity === null
                    ? null
                    : max(0, (int) $orderProduct->planned_fill_quantity)))
            : (int) $orderProduct->quantity;
        $base = [
            'order_product_id' => $orderProduct->getKey(),
            'product_id' => $product?->getKey(),
            'sku' => $product?->sku ?? 'CUSTOM',
            'name' => $product?->name ?? ($orderProduct->custom_description ?: 'Custom product'),
            'unit_of_measure' => $product?->unit?->value ?? 'unit',
            'quantity' => $quantity,
            'fill_load' => $isFillLoad,
            'fill_priority' => $orderProduct->fill_priority,
            'fill_locked_at' => $orderProduct->fill_locked_at?->toAtomString(),
            'fill_plan_source' => $isFillLoad
                ? ($orderProduct->fill_locked_at
                    ? 'locked'
                    : ($orderProduct->fill_plan_source === 'manual'
                        ? 'manual'
                        : ($hasFillOverride ? 'automatic' : null)))
                : null,
            'fill_resolved' => ! $isFillLoad || $quantity !== null,
            'loading_profile' => $product?->loadingProfile?->code,
            'loading_profile_name' => $product?->loadingProfile?->name,
            'handling_method' => $product?->loadingProfile?->handling_method,
            'units_per_pallet' => $product?->loadingProfile?->units_per_pallet,
            'pallet_compatibility_group' => $product?->loadingProfile?->pallet_compatibility_group,
            'rack_requirement' => $product?->loadingProfile?->rack_requirement,
            'required_rack_level' => $product?->loadingProfile?->required_rack_level,
            'required_rack_type' => $this->requiredRackTypeCode($product?->loadingProfile),
            'required_rack_level_count' => $this->requiredRackTypeLevelCount($product?->loadingProfile),
            'allowed_rack_type_codes' => $this->allowedRackTypeCodes($product?->loadingProfile),
            'allowed_rack_types' => $this->allowedRackTypes($product?->loadingProfile),
            'placement_strategy' => $product?->loadingProfile?->placement_strategy
                ?? LoadingProfile::PLACEMENT_ONE_PER_LEVEL,
            'units_per_rack_position' => $product?->loadingProfile?->units_per_rack_position ?? 1,
            'flatbed_fallback_units_per_spot' => $product?->loadingProfile?->flatbed_fallback_units_per_spot,
            'full_load_units' => $product?->loadingProfile?->full_load_units,
            'unit_weight_lbs' => $product?->weight_lbs === null ? null : (float) $product->weight_lbs,
            'total_weight_lbs' => null,
            'pallet_equivalent' => null,
        ];

        if ($isFillLoad && $quantity === null) {
            $warnings[] = $this->warning(
                'fill_load_quantity_required',
                "{$base['sku']} is marked Fill load, but a safe planned quantity could not be calculated.",
                $order,
                $base['sku'],
            );

            return $base;
        }

        if ($isFillLoad && $quantity === 0) {
            if (! $product || $orderProduct->is_custom_product) {
                $warnings[] = $this->warning(
                    'custom_product',
                    "{$base['name']} needs a loading profile before automatic placement.",
                    $order,
                    $base['sku'],
                );

                return $base;
            }

            if (! $product->loadingProfile) {
                $warnings[] = $this->warning(
                    'missing_loading_profile',
                    "{$product->sku} does not have a loading profile.",
                    $order,
                    $product->sku,
                );
            }

            if ($product->weight_lbs === null) {
                $warnings[] = $this->warning(
                    'missing_weight',
                    "{$product->sku} does not have a shipping weight.",
                    $order,
                    $product->sku,
                );
            }

            return $base;
        }

        if ($quantity === null || $quantity <= 0) {
            $warnings[] = $this->warning(
                'invalid_quantity',
                "{$base['sku']} does not have a positive planning quantity.",
                $order,
                $base['sku'],
            );

            return $base;
        }

        $summary['product_units'] += $quantity;

        if (! $product || $orderProduct->is_custom_product) {
            $warnings[] = $this->warning(
                'custom_product',
                "{$base['name']} needs a loading profile before automatic placement.",
                $order,
                $base['sku'],
            );

            return $base;
        }

        $profile = $product->loadingProfile;

        if (! $profile) {
            $warnings[] = $this->warning(
                'missing_loading_profile',
                "{$product->sku} does not have a loading profile.",
                $order,
                $product->sku,
            );
        } else {
            $this->addProfileDemand($product->getKey(), $profile, $quantity, $summary, $palletBuckets, $warnings, $order, $product->sku, $base);
        }

        if ($product->weight_lbs === null) {
            $summary['unknown_weight_items']++;
            $warnings[] = $this->warning(
                'missing_weight',
                "{$product->sku} does not have a shipping weight.",
                $order,
                $product->sku,
            );
        } else {
            $base['total_weight_lbs'] = round((float) $product->weight_lbs * $quantity, 2);
            $summary['known_weight_lbs'] += $base['total_weight_lbs'];
        }

        return $base;
    }

    private function addProfileDemand(
        int|string $productId,
        LoadingProfile $profile,
        int $quantity,
        array &$summary,
        array &$palletBuckets,
        array &$warnings,
        Order $order,
        string $sku,
        array &$item,
    ): void {
        if ($profile->handling_method === LoadingProfile::HANDLING_PALLET) {
            if (! $profile->units_per_pallet) {
                $warnings[] = $this->warning(
                    'missing_units_per_pallet',
                    "{$sku} is palletized but its products-per-pallet value is missing.",
                    $order,
                    $sku,
                );

                return;
            }

            $palletEquivalent = $quantity / $profile->units_per_pallet;
            $bucket = filled($profile->pallet_compatibility_group)
                ? "group:{$profile->pallet_compatibility_group}"
                : "product:{$productId}";
            $palletBuckets[$bucket] = ($palletBuckets[$bucket] ?? 0) + $palletEquivalent;
            $item['pallet_equivalent'] = round($palletEquivalent, 4);

            return;
        }

        if ($profile->rack_requirement === LoadingProfile::RACK_SINGLE) {
            $summary['oversized_rack_spots'] += $quantity;

            return;
        }

        if ($profile->rack_requirement === LoadingProfile::RACK_STANDARD) {
            $summary['standard_box_units'] += $quantity;
        }
    }

    private function warning(string $code, string $message, Order $order, string $sku): array
    {
        return [
            'code' => $code,
            'message' => $message,
            'blocking' => true,
            'order_id' => $order->getKey(),
            'order_number' => $order->order_number,
            'sku' => $sku,
        ];
    }

    private function requiredRackTypeCode(?LoadingProfile $profile): ?string
    {
        if (! $profile || ! $profile->required_rack_type_id) {
            return null;
        }

        return $profile->requiredRackType?->code;
    }

    private function requiredRackTypeLevelCount(?LoadingProfile $profile): ?int
    {
        if (! $profile || ! $profile->required_rack_type_id) {
            return null;
        }

        return $profile->requiredRackType?->level_count;
    }

    private function allowedRackTypeCodes(?LoadingProfile $profile): array
    {
        if (! $profile) {
            return [];
        }

        $codes = $profile->relationLoaded('allowedRackTypes')
            ? $profile->allowedRackTypes->pluck('code')->filter()->values()->all()
            : [];

        if ($codes === [] && $this->requiredRackTypeCode($profile)) {
            $codes[] = $this->requiredRackTypeCode($profile);
        }

        return array_values(array_unique($codes));
    }

    private function allowedRackTypes(?LoadingProfile $profile): array
    {
        if (! $profile) {
            return [];
        }

        $rackTypes = $profile->relationLoaded('allowedRackTypes')
            ? $profile->allowedRackTypes
            : collect();

        $requiredRackType = $profile->relationLoaded('requiredRackType')
            ? $profile->requiredRackType
            : null;

        if ($rackTypes->isEmpty() && $requiredRackType) {
            $rackTypes = collect([$requiredRackType]);
        }

        return $rackTypes
            ->unique('code')
            ->map(fn ($rackType): array => [
                'code' => $rackType->code,
                'level_count' => (int) $rackType->level_count,
                'pallet_capable_levels' => (int) $rackType->pallet_capable_levels,
                'pallets_per_capable_level' => (int) $rackType->pallets_per_capable_level,
            ])
            ->values()
            ->all();
    }

    private function fullLoadProfileTotals(array $stops): array
    {
        $totals = [];

        foreach ($stops as $stop) {
            foreach ($stop['items'] as $item) {
                if (! $item['loading_profile'] || ! $item['full_load_units'] || ! $item['quantity']) {
                    continue;
                }

                $code = $item['loading_profile'];
                $totals[$code] ??= [
                    'profile_code' => $code,
                    'profile_name' => $item['loading_profile_name'] ?: $code,
                    'quantity' => 0,
                    'full_load_units' => (int) $item['full_load_units'],
                ];
                $totals[$code]['quantity'] += (int) $item['quantity'];
            }
        }

        return array_values($totals);
    }
}
