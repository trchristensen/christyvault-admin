<?php

namespace App\Services\LoadPlanning;

use App\Models\Location;
use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\Product;
use App\Models\Trip;
use App\Models\TripStop;
use App\Models\VehicleConfiguration;
use App\Services\TripVehicleConfigurationResolver;
use Illuminate\Support\Collection;

final class DraftOrderLoadPreviewService
{
    private const DRAFT_ORDER_ID = -2147483000;

    public function __construct(
        private readonly TripLoadPlanService $loadPlanService,
        private readonly TripVehicleConfigurationResolver $vehicleConfigurationResolver,
    ) {}

    public function forForm(?Order $record, array $state): array
    {
        $draftOrder = $this->draftOrder($record, $state);
        $sourceTrip = $record?->trip_id
            ? Trip::query()
                ->with([
                    'vehicleConfiguration',
                    'stops.order.location',
                    'stops.order.orderProducts.product.loadingProfile.requiredRackType',
                    'stops.order.orderProducts.product.loadingProfile.allowedRackTypes',
                    'orders.location',
                    'orders.orderProducts.product.loadingProfile.requiredRackType',
                    'orders.orderProducts.product.loadingProfile.allowedRackTypes',
                ])
                ->find($record->trip_id)
            : null;
        $vehicleConfiguration = $sourceTrip?->vehicleConfiguration
            ?? VehicleConfiguration::query()->find($state['vehicle_configuration_id'] ?? null)
            ?? $this->vehicleConfigurationResolver->defaultForOrders([$draftOrder]);

        if (! $vehicleConfiguration) {
            return $this->unavailable(
                'No active vehicle configuration is available for this preview.',
                $draftOrder,
            );
        }

        return $this->preview($draftOrder, $sourceTrip, $vehicleConfiguration);
    }

    public function preview(
        Order $draftOrder,
        ?Trip $sourceTrip,
        VehicleConfiguration $vehicleConfiguration,
    ): array {
        if ($sourceTrip?->exists) {
            $sourceTrip->loadMissing([
                'vehicleConfiguration',
                'stops.order.location',
                'stops.order.orderProducts.product.loadingProfile.requiredRackType',
                'stops.order.orderProducts.product.loadingProfile.allowedRackTypes',
                'orders.location',
                'orders.orderProducts.product.loadingProfile.requiredRackType',
                'orders.orderProducts.product.loadingProfile.allowedRackTypes',
            ]);
        }

        $orders = $sourceTrip
            ? $sourceTrip->orderedDeliveryOrders()
            : collect();
        $draftWasReplaced = false;

        $orders = $orders
            ->map(function (Order $order) use ($draftOrder, &$draftWasReplaced): Order {
                if ($order->getKey() !== $draftOrder->getKey()) {
                    return $order;
                }

                $draftWasReplaced = true;

                return $draftOrder;
            })
            ->values();

        if (! $draftWasReplaced) {
            $orders->push($draftOrder);
        }

        $draftStopSequence = $orders
            ->search(fn (Order $order): bool => $order === $draftOrder) + 1;
        $trip = $this->syntheticTrip(
            $orders,
            $vehicleConfiguration,
            $sourceTrip?->trip_number ?? 'DRAFT LOAD',
        );
        $plan = $this->loadPlanService->forTrip($trip);
        $baseOrders = $orders
            ->reject(fn (Order $order): bool => $order === $draftOrder)
            ->values();
        $basePlan = $this->loadPlanService->forTrip(
            $this->syntheticTrip(
                $baseOrders,
                $vehicleConfiguration,
                $sourceTrip?->trip_number ?? 'DRAFT LOAD',
            ),
        );

        return $this->formatPreview(
            $draftOrder,
            $sourceTrip,
            $vehicleConfiguration,
            $draftStopSequence,
            $plan,
            $basePlan,
        );
    }

    private function draftOrder(?Order $record, array $state): Order
    {
        $order = new Order([
            'order_number' => $record?->order_number ?? 'DRAFT ORDER',
            'order_date' => $state['order_date'] ?? now()->toDateString(),
            'location_id' => $state['location_id'] ?? null,
            'trip_id' => $record?->trip_id,
            'stop_number' => $record?->stop_number,
        ]);
        $order->id = $record?->getKey() ?? self::DRAFT_ORDER_ID;
        $order->setRelation(
            'location',
            filled($state['location_id'] ?? null)
                ? Location::query()->find($state['location_id'])
                : null,
        );

        $productStates = collect($state['orderProducts'] ?? [])
            ->filter(fn ($line): bool => is_array($line))
            ->values();
        $products = Product::query()
            ->with([
                'loadingProfile.requiredRackType',
                'loadingProfile.allowedRackTypes',
            ])
            ->whereKey($productStates->pluck('product_id')->filter()->unique())
            ->get()
            ->keyBy(fn (Product $product): string => (string) $product->getKey());
        $lines = $productStates
            ->map(function (array $lineState, int $index) use ($products): OrderProduct {
                $line = new OrderProduct([
                    'product_id' => $lineState['product_id'] ?? null,
                    'is_custom_product' => (bool) ($lineState['is_custom_product'] ?? false),
                    'custom_description' => $lineState['custom_description'] ?? null,
                    'quantity' => $lineState['quantity'] ?? null,
                    'fill_load' => (bool) ($lineState['fill_load'] ?? false),
                    'planned_fill_quantity' => filled($lineState['planned_fill_quantity'] ?? null)
                        ? (int) $lineState['planned_fill_quantity']
                        : null,
                    'fill_priority' => filled($lineState['fill_priority'] ?? null)
                        ? (int) $lineState['fill_priority']
                        : null,
                    'fill_plan_source' => $lineState['fill_plan_source'] ?? null,
                    'fill_locked_at' => $lineState['fill_locked_at'] ?? null,
                ]);
                $line->id = self::DRAFT_ORDER_ID - $index - 1;
                $line->setRelation(
                    'product',
                    $products->get((string) ($lineState['product_id'] ?? '')),
                );

                return $line;
            });

        $order->setRelation('orderProducts', $lines);

        return $order;
    }

    private function syntheticTrip(
        Collection $orders,
        VehicleConfiguration $vehicleConfiguration,
        string $tripNumber,
    ): Trip {
        $stops = $orders
            ->values()
            ->map(function (Order $order, int $index): TripStop {
                $stop = new TripStop([
                    'order_id' => $order->getKey(),
                    'sequence' => $index + 1,
                ]);
                $stop->setRelation('order', $order);

                return $stop;
            });
        $trip = new Trip(['trip_number' => $tripNumber]);
        $trip->setRelation('vehicleConfiguration', $vehicleConfiguration);
        $trip->setRelation('stops', $stops);
        $trip->setRelation('orders', $orders->values());

        return $trip;
    }

    private function formatPreview(
        Order $draftOrder,
        ?Trip $sourceTrip,
        VehicleConfiguration $vehicleConfiguration,
        int $draftStopSequence,
        array $plan,
        array $basePlan,
    ): array {
        $demand = $plan['demand'];
        $diagram = $plan['diagram'];
        $baseDemand = $basePlan['demand'];
        $baseDiagram = $basePlan['diagram'];
        $draftStop = collect($demand->stops)->firstWhere('sequence', $draftStopSequence);
        $draftSummary = $draftStop['summary'] ?? [
            'product_units' => 0,
            'known_weight_lbs' => 0,
            'unknown_weight_items' => 0,
        ];
        $hasDraftLines = $draftOrder->orderProducts->isNotEmpty();
        $unplaced = collect($diagram['unplaced'] ?? []);
        $blockingWarnings = collect($demand->warnings)
            ->where('blocking', true);
        $isRackTrailer = $vehicleConfiguration->configuration_type === VehicleConfiguration::TYPE_RACK_TRAILER;
        $fits = $hasDraftLines
            && $demand->isReadyForAutomaticPlacement()
            && ($diagram['available'] ?? false)
            && $unplaced->isEmpty()
            && ! ($demand->summary['is_overweight'] ?? false);
        $status = match (true) {
            ! $hasDraftLines => 'empty',
            ($demand->summary['is_overweight'] ?? false) || $unplaced->isNotEmpty() => 'does_not_fit',
            ! $isRackTrailer => 'weight_only',
            $blockingWarnings->isNotEmpty() => 'review',
            $fits => 'fits',
            default => 'review',
        };
        $warningMessages = $blockingWarnings
            ->pluck('message')
            ->merge($unplaced->map(fn (array $item): string => sprintf(
                '%s: %s',
                $item['sku'] ?? 'Product',
                $item['reason'] ?? 'Manual placement is required.',
            )))
            ->filter()
            ->unique()
            ->values()
            ->all();
        $rackCapacity = max(0, (int) ($diagram['rack_spot_count']
            ?? $vehicleConfiguration->rack_spot_count
            ?? 0));
        $flatbedCapacity = max(0, (int) ($diagram['flatbed_pallet_capacity']
            ?? $vehicleConfiguration->flatbed_pallet_capacity
            ?? 0));
        $usedRacks = (int) ($diagram['used_rack_spots'] ?? 0);
        $existingUsedRacks = (int) ($baseDiagram['used_rack_spots'] ?? 0);
        $usedFlatbed = (int) ($diagram['flatbed_pallets_used'] ?? 0);
        $existingUsedFlatbed = (int) ($baseDiagram['flatbed_pallets_used'] ?? 0);
        $draftRacksTouched = collect($diagram['racks'] ?? [])
            ->filter(fn (array $rack): bool => in_array(
                $draftStopSequence,
                $rack['stop_sequences'] ?? [],
                true,
            ))
            ->count();

        return [
            'status' => $status,
            'status_label' => match ($status) {
                'fits' => 'Fits',
                'does_not_fit' => 'Does not fit',
                'weight_only' => 'Weight only',
                'review' => 'Review',
                default => 'Add products',
            },
            'context' => [
                'trip_number' => $sourceTrip?->trip_number,
                'vehicle_name' => $vehicleConfiguration->name,
                'existing_stops' => max(0, count($demand->stops) - 1),
                'draft_stop_sequence' => $draftStopSequence,
            ],
            'weight' => [
                'known' => (float) ($demand->summary['known_weight_lbs'] ?? 0),
                'maximum' => $demand->summary['maximum_product_weight_lbs'],
                'remaining' => $demand->summary['remaining_product_weight_lbs'],
                'existing' => (float) ($baseDemand->summary['known_weight_lbs'] ?? 0),
                'draft' => (float) ($draftSummary['known_weight_lbs'] ?? 0),
                'unknown_items' => (int) ($demand->summary['unknown_weight_items'] ?? 0),
            ],
            'racks' => [
                'used' => $usedRacks,
                'capacity' => $rackCapacity,
                'remaining' => max(0, $rackCapacity - $usedRacks),
                'existing_used' => $existingUsedRacks,
                'draft_added' => max(0, $usedRacks - $existingUsedRacks),
                'draft_touched' => $draftRacksTouched,
            ],
            'flatbed' => [
                'used' => $usedFlatbed,
                'capacity' => $flatbedCapacity,
                'remaining' => max(0, $flatbedCapacity - $usedFlatbed),
                'existing_used' => $existingUsedFlatbed,
                'draft_added' => max(0, $usedFlatbed - $existingUsedFlatbed),
            ],
            'draft' => [
                'line_count' => $draftOrder->orderProducts->count(),
                'product_units' => (int) ($draftSummary['product_units'] ?? 0),
                'known_weight_lbs' => (float) ($draftSummary['known_weight_lbs'] ?? 0),
            ],
            'diagram' => $diagram,
            'warnings' => $warningMessages,
            'message' => match ($status) {
                'fits' => 'All configured products fit.',
                'does_not_fit' => 'This draft needs another truck or a manual load adjustment.',
                'weight_only' => $diagram['message'] ?? 'Rack placement is not available for this truck.',
                'review' => 'Some product data needs review before fit can be confirmed.',
                default => 'Add products to see their effect on the truck.',
            },
        ];
    }

    private function unavailable(string $message, Order $draftOrder): array
    {
        return [
            'status' => 'review',
            'status_label' => 'Unavailable',
            'context' => [
                'trip_number' => null,
                'vehicle_name' => 'No truck selected',
                'existing_stops' => 0,
                'draft_stop_sequence' => 1,
            ],
            'weight' => [
                'known' => 0,
                'maximum' => null,
                'remaining' => null,
                'existing' => 0,
                'draft' => 0,
                'unknown_items' => 0,
            ],
            'racks' => [
                'used' => 0,
                'capacity' => 0,
                'remaining' => 0,
                'existing_used' => 0,
                'draft_added' => 0,
                'draft_touched' => 0,
            ],
            'flatbed' => [
                'used' => 0,
                'capacity' => 0,
                'remaining' => 0,
                'existing_used' => 0,
                'draft_added' => 0,
            ],
            'draft' => [
                'line_count' => $draftOrder->orderProducts->count(),
                'product_units' => 0,
                'known_weight_lbs' => 0,
            ],
            'diagram' => [
                'available' => false,
                'racks' => [],
                'flatbed_pallets' => [],
            ],
            'warnings' => [$message],
            'message' => $message,
        ];
    }
}
