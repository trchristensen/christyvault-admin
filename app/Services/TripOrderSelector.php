<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Trip;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class TripOrderSelector
{
    private const DEFAULT_WINDOW_DAYS = 90;

    private const RESULT_LIMIT = 50;

    public function options(
        mixed $currentRecord,
        CarbonInterface|string|null $scheduledDate,
        ?string $search = null,
    ): array {
        $targetDate = $this->targetDate($scheduledDate);
        $query = $this->availableOrdersQuery($currentRecord);
        $search = trim((string) $search);

        if ($search !== '') {
            $this->applySearch($query, $search);
        } else {
            $this->applyDefaultDateWindow($query, $targetDate);
        }

        $orders = $query
            ->with('location')
            ->get();

        return $this->sortByDateProximity($orders, $targetDate)
            ->take(self::RESULT_LIMIT)
            ->mapWithKeys(fn (Order $order): array => [$order->getKey() => $this->renderLabel($order)])
            ->all();
    }

    public function labelForValue(int|string|null $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        $order = Order::query()->with('location')->find($value);

        return $order ? $this->renderLabel($order) : null;
    }

    private function availableOrdersQuery(mixed $currentRecord): Builder
    {
        $currentOrder = $currentRecord instanceof Order ? $currentRecord : null;
        $currentTrip = $currentRecord instanceof Trip ? $currentRecord : null;

        return Order::query()
            ->where(function (Builder $query) use ($currentOrder, $currentTrip): void {
                $query->whereNull('trip_id');

                if ($currentOrder) {
                    $query->orWhereKey($currentOrder->getKey());
                }

                if ($currentTrip) {
                    $query->orWhere('trip_id', $currentTrip->getKey());
                }

                $query->orWhereHas('trip', function (Builder $query): void {
                    $query->whereNull('deleted_at')
                        ->has('orders', '=', 1);
                });
            })
            ->whereNotIn('status', ['delivered', 'cancelled']);
    }

    private function applySearch(Builder $query, string $search): void
    {
        $pattern = '%'.$search.'%';

        $query->where(function (Builder $query) use ($pattern): void {
            $query->whereLike('order_number', $pattern, caseSensitive: false)
                ->orWhereLike('customer_order_number', $pattern, caseSensitive: false)
                ->orWhereHas('location', function (Builder $query) use ($pattern): void {
                    $query->whereLike('name', $pattern, caseSensitive: false)
                        ->orWhereLike('address_line1', $pattern, caseSensitive: false)
                        ->orWhereLike('city', $pattern, caseSensitive: false)
                        ->orWhereLike('state', $pattern, caseSensitive: false)
                        ->orWhereLike('postal_code', $pattern, caseSensitive: false);
                });
        });
    }

    private function applyDefaultDateWindow(Builder $query, CarbonImmutable $targetDate): void
    {
        $start = $targetDate->subDays(self::DEFAULT_WINDOW_DAYS)->toDateString();
        $end = $targetDate->addDays(self::DEFAULT_WINDOW_DAYS)->toDateString();

        $query->where(function (Builder $query) use ($start, $end): void {
            $query->whereBetween('assigned_delivery_date', [$start, $end])
                ->orWhere(function (Builder $query) use ($start, $end): void {
                    $query->whereNull('assigned_delivery_date')
                        ->whereBetween('requested_delivery_date', [$start, $end]);
                })
                ->orWhere(function (Builder $query) use ($start, $end): void {
                    $query->whereNull('assigned_delivery_date')
                        ->whereNull('requested_delivery_date')
                        ->whereBetween('order_date', [$start, $end]);
                });
        });
    }

    private function sortByDateProximity(Collection $orders, CarbonImmutable $targetDate): Collection
    {
        return $orders->sort(function (Order $left, Order $right) use ($targetDate): int {
            $leftDate = $this->effectiveDate($left);
            $rightDate = $this->effectiveDate($right);
            $distanceOrder = $this->dateDistance($leftDate, $targetDate)
                <=> $this->dateDistance($rightDate, $targetDate);

            if ($distanceOrder !== 0) {
                return $distanceOrder;
            }

            $pastOrder = ($leftDate?->isBefore($targetDate) ?? true)
                <=> ($rightDate?->isBefore($targetDate) ?? true);

            if ($pastOrder !== 0) {
                return $pastOrder;
            }

            return ($right->getKey() ?? 0) <=> ($left->getKey() ?? 0);
        })->values();
    }

    private function targetDate(CarbonInterface|string|null $scheduledDate): CarbonImmutable
    {
        if ($scheduledDate instanceof CarbonInterface) {
            return CarbonImmutable::instance($scheduledDate)->startOfDay();
        }

        if (filled($scheduledDate)) {
            try {
                return CarbonImmutable::parse($scheduledDate)->startOfDay();
            } catch (\Throwable) {
                // Fall back to today while the date field is being edited.
            }
        }

        return CarbonImmutable::today();
    }

    private function effectiveDate(Order $order): ?CarbonImmutable
    {
        $date = $order->assigned_delivery_date
            ?? $order->requested_delivery_date
            ?? $order->order_date;

        return $date ? CarbonImmutable::instance($date)->startOfDay() : null;
    }

    private function dateDistance(?CarbonImmutable $date, CarbonImmutable $targetDate): int
    {
        return $date ? (int) abs($targetDate->diffInDays($date, false)) : PHP_INT_MAX;
    }

    private function renderLabel(Order $order): string
    {
        return view('filament.components.order-option', [
            'orderNumber' => $order->order_number,
            'customerName' => $order->location?->name,
            'status' => $order->status,
            'requestedDeliveryDate' => $order->requested_delivery_date?->format('M j, Y'),
            'assignedDeliveryDate' => $order->assigned_delivery_date?->format('M j, Y'),
            'location_line1' => $order->location?->address_line1,
            'location_line2' => $order->location
                ? "{$order->location->city}, {$order->location->state} {$order->location->postal_code}"
                : '',
        ])->render();
    }
}
