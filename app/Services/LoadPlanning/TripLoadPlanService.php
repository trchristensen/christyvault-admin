<?php

namespace App\Services\LoadPlanning;

use App\Models\LoadingProfile;
use App\Models\OrderProduct;
use App\Models\Trip;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class TripLoadPlanService
{
    private const MAX_AUTOMATIC_FILL_UNITS = 500;

    public function __construct(
        private readonly LoadDemandService $demandService,
        private readonly RackDiagramService $diagramService,
    ) {}

    public function forTrip(Trip $trip): array
    {
        $initialDemand = $this->demandService->forTrip($trip);
        $fillLines = $this->fillLines($trip);

        if ($fillLines->isEmpty()) {
            return $this->result(
                $initialDemand,
                $this->diagramService->forDemand($initialDemand),
                [],
            );
        }

        $fillQuantities = $fillLines
            ->mapWithKeys(fn (array $candidate): array => [
                $candidate['line']->getKey() => $candidate['line']->planned_fill_quantity ?? 0,
            ])
            ->all();
        $baseDemand = $this->demandService->forTrip($trip, $fillQuantities);
        $baseDiagram = $this->diagramService->forDemand($baseDemand);

        if (! $this->fits($baseDemand, $baseDiagram)) {
            return $this->result(
                $initialDemand,
                $this->diagramService->forDemand($initialDemand),
                $this->allocations($initialDemand, $fillLines),
            );
        }

        $automaticCandidates = $fillLines
            ->filter(fn (array $candidate): bool => $candidate['line']->planned_fill_quantity === null)
            ->sortBy(fn (array $candidate): array => $this->fillSortKey($candidate))
            ->values();

        foreach ($automaticCandidates as $candidate) {
            $lineId = $candidate['line']->getKey();
            $limit = $this->candidateLimit($baseDemand, $lineId);

            for ($quantity = 1; $quantity <= $limit; $quantity++) {
                $trialQuantities = array_replace($fillQuantities, [$lineId => $quantity]);
                $trialDemand = $this->demandService->forTrip($trip, $trialQuantities);
                $trialDiagram = $this->diagramService->forDemand($trialDemand);

                if (! $this->fits($trialDemand, $trialDiagram)) {
                    break;
                }

                $fillQuantities[$lineId] = $quantity;
            }
        }

        $demand = $this->demandService->forTrip($trip, $fillQuantities);
        $diagram = $this->diagramService->forDemand($demand);

        return $this->result($demand, $diagram, $this->allocations($demand, $fillLines));
    }

    public function lockFillPlan(Trip $trip): array
    {
        return DB::transaction(function () use ($trip): array {
            $orderIds = $trip->orders()->pluck('id');

            if (! OrderProduct::query()
                ->whereIn('order_id', $orderIds)
                ->where('fill_load', true)
                ->exists()) {
                return [];
            }

            $plan = $this->forTrip($trip);
            $allocations = collect($plan['fill_allocations']);

            if ($allocations->contains(fn (array $allocation): bool => ! $allocation['resolved'])
                || ($allocations->isNotEmpty() && ! $this->fits($plan['demand'], $plan['diagram']))) {
                throw ValidationException::withMessages([
                    'fill_load' => 'The Fill load quantities could not be calculated safely. Review the load summary before confirming dispatch.',
                ]);
            }

            foreach ($allocations as $allocation) {
                $line = OrderProduct::query()->findOrFail($allocation['order_product_id']);
                $line->update([
                    'planned_fill_quantity' => $allocation['planned_quantity'],
                    'fill_plan_source' => $line->fill_plan_source === 'manual' ? 'manual' : 'automatic',
                    'fill_locked_at' => now(),
                ]);
            }

            return $this->forTrip($trip->refresh());
        });
    }

    public function unlockFillPlan(Trip $trip): void
    {
        $orderIds = $trip->orders()->pluck('id');

        foreach (OrderProduct::query()
            ->whereIn('order_id', $orderIds)
            ->where('fill_load', true)
            ->get() as $line) {
            $automaticallyPlanned = $line->fill_plan_source === 'automatic';
            $line->update([
                'planned_fill_quantity' => $automaticallyPlanned ? null : $line->planned_fill_quantity,
                'fill_plan_source' => $automaticallyPlanned ? null : $line->fill_plan_source,
                'fill_locked_at' => null,
            ]);
        }
    }

    private function fillLines(Trip $trip): Collection
    {
        return $trip->orderedDeliveryOrders()
            ->values()
            ->flatMap(function ($order, int $stopIndex): array {
                return $order->orderProducts
                    ->where('fill_load', true)
                    ->map(fn (OrderProduct $line): array => [
                        'line' => $line,
                        'order' => $order,
                        'stop_sequence' => $stopIndex + 1,
                    ])
                    ->values()
                    ->all();
            });
    }

    private function candidateLimit(LoadDemandResult $demand, int $orderProductId): int
    {
        $item = collect($demand->stops)
            ->flatMap(fn (array $stop): array => $stop['items'])
            ->firstWhere('order_product_id', $orderProductId);

        if (! $item) {
            return 0;
        }

        $rackSpots = max(1, (int) ($demand->vehicleConfiguration['rack_spot_count'] ?? 0));
        $physicalLimit = match (true) {
            $item['handling_method'] === LoadingProfile::HANDLING_PALLET => $rackSpots
                * 4
                * max(1, (int) ($item['units_per_pallet'] ?? 1)),
            $item['rack_requirement'] === LoadingProfile::RACK_SINGLE => $rackSpots,
            default => $rackSpots * 3 * max(1, (int) ($item['units_per_rack_position'] ?? 1)),
        };
        $profileLimit = (int) ($item['full_load_units'] ?? 0);
        $limit = max($physicalLimit, $profileLimit);

        if (($item['unit_weight_lbs'] ?? 0) > 0
            && ($demand->summary['maximum_product_weight_lbs'] ?? 0) > 0) {
            $weightLimit = (int) floor(
                $demand->summary['maximum_product_weight_lbs'] / $item['unit_weight_lbs'],
            );
            $limit = min($limit, $weightLimit);
        }

        return min(self::MAX_AUTOMATIC_FILL_UNITS, max(0, $limit));
    }

    private function fits(LoadDemandResult $demand, array $diagram): bool
    {
        return $demand->isReadyForAutomaticPlacement()
            && ($diagram['available'] ?? false)
            && ($diagram['unplaced'] ?? []) === []
            && (int) ($diagram['placed_units'] ?? 0) === (int) ($demand->summary['product_units'] ?? 0);
    }

    private function allocations(LoadDemandResult $demand, Collection $fillLines): array
    {
        $items = collect($demand->stops)
            ->flatMap(fn (array $stop): array => $stop['items'])
            ->keyBy('order_product_id');

        return $fillLines
            ->sortBy(fn (array $candidate): array => $this->fillSortKey($candidate))
            ->values()
            ->map(function (array $candidate, int $index) use ($items): array {
                $line = $candidate['line'];
                $item = $items->get($line->getKey(), []);

                return [
                    'order_product_id' => $line->getKey(),
                    'order_id' => $candidate['order']->getKey(),
                    'order_number' => $candidate['order']->order_number,
                    'stop_sequence' => $candidate['stop_sequence'],
                    'sku' => $item['sku'] ?? $line->product?->sku ?? 'CUSTOM',
                    'name' => $item['name'] ?? $line->product?->name ?? $line->custom_description,
                    'priority' => $line->fill_priority ?? ($index + 1),
                    'planned_quantity' => $item['quantity'] ?? $line->planned_fill_quantity,
                    'resolved' => (bool) ($item['fill_resolved'] ?? false),
                    'source' => $item['fill_plan_source'] ?? null,
                    'locked_at' => $line->fill_locked_at?->toAtomString(),
                ];
            })
            ->all();
    }

    private function fillSortKey(array $candidate): array
    {
        $order = $candidate['order'];

        return [
            $candidate['line']->fill_priority ?? PHP_INT_MAX,
            $order->order_date?->format('Y-m-d') ?? '9999-12-31',
            $order->created_at?->getTimestamp() ?? PHP_INT_MAX,
            $order->getKey(),
            $candidate['line']->getKey(),
        ];
    }

    private function result(LoadDemandResult $demand, array $diagram, array $allocations): array
    {
        return [
            'demand' => $demand,
            'diagram' => $diagram,
            'fill_allocations' => $allocations,
        ];
    }
}
