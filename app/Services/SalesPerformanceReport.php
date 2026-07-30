<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\SalesVisitStatus;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SalesPerformanceReport
{
    public function build(
        string $locationId = 'all',
        string $timeframe = 'year_over_year',
        string $metric = 'units',
        string $productType = 'all',
        array $plants = ['colma_main', 'colma_locals', 'tulare_plant'],
        ?CarbonInterface $asOf = null,
    ): array {
        $metric = in_array($metric, ['units', 'revenue'], true) ? $metric : 'units';
        $plantLocations = $this->plantLocations($plants);
        $asOf = $asOf
            ? CarbonImmutable::instance($asOf)->endOfDay()
            : CarbonImmutable::now()->endOfDay();
        $period = $this->period($timeframe, $asOf);

        $currentMonthly = $this->monthlyTotals(
            $period['currentStart'],
            $period['currentEnd'],
            $locationId,
            $productType,
            $metric,
            $plantLocations,
        );
        $previousMonthly = $this->monthlyTotals(
            $period['previousStart'],
            $period['previousEnd'],
            $locationId,
            $productType,
            $metric,
            $plantLocations,
        );

        $months = $this->monthsBetween($period['currentStart'], $period['currentEnd']);
        $currentData = [];
        $previousData = [];
        $labels = [];

        foreach ($months as $month) {
            $labels[] = $month->format('M');
            $currentData[] = $currentMonthly->get($month->format('Y-m'), 0);
            $previousData[] = $previousMonthly->get($month->subYear()->format('Y-m'), 0);
        }

        $currentValue = (float) array_sum($currentData);
        $previousValue = (float) array_sum($previousData);
        $change = $currentValue - $previousValue;
        $changePercent = $previousValue > 0
            ? ($change / $previousValue) * 100
            : null;

        return [
            'chart' => [
                'labels' => $labels,
                'datasets' => [
                    [
                        'label' => $period['previousLabel'],
                        'data' => $previousData,
                        'backgroundColor' => '#CBD5E1',
                        'borderColor' => '#64748B',
                        'borderWidth' => 1,
                        'borderRadius' => 4,
                    ],
                    [
                        'label' => $period['currentLabel'],
                        'data' => $currentData,
                        'backgroundColor' => '#2563EB',
                        'borderColor' => '#1D4ED8',
                        'borderWidth' => 1,
                        'borderRadius' => 4,
                    ],
                ],
            ],
            'chartMeta' => [
                'metric' => $metric,
                'axisLabel' => $metric === 'revenue' ? 'Order Revenue' : 'Products Ordered',
            ],
            'summary' => [
                'currentValue' => $currentValue,
                'previousValue' => $previousValue,
                'change' => $change,
                'changePercent' => $changePercent,
                'currentLabel' => $period['currentLabel'],
                'previousLabel' => $period['previousLabel'],
                'currentOrders' => $this->orderCount(
                    $period['currentStart'],
                    $period['currentEnd'],
                    $locationId,
                    $productType,
                    $plantLocations,
                ),
                'previousOrders' => $this->orderCount(
                    $period['previousStart'],
                    $period['previousEnd'],
                    $locationId,
                    $productType,
                    $plantLocations,
                ),
                'completedVisits' => $this->completedVisitCount(
                    $period['currentStart'],
                    $period['currentEnd'],
                    $locationId,
                    $plantLocations,
                ),
                'previousCompletedVisits' => $this->completedVisitCount(
                    $period['previousStart'],
                    $period['previousEnd'],
                    $locationId,
                    $plantLocations,
                ),
                'dataThrough' => $this->dataThrough(
                    $period['currentStart'],
                    $period['currentEnd'],
                    $locationId,
                    $productType,
                    $plantLocations,
                ),
            ],
            'breakdown' => [
                'level' => $productType === 'all' ? 'category' : 'product',
                'scope' => $productType,
                'rows' => $this->breakdown(
                    $period,
                    $locationId,
                    $productType,
                    $metric,
                    $plantLocations,
                ),
            ],
            'period' => $period,
        ];
    }

    private function period(string $timeframe, CarbonImmutable $asOf): array
    {
        $currentStart = match ($timeframe) {
            'last_12_months' => $asOf->startOfMonth()->subMonths(11),
            '6months' => $asOf->startOfMonth()->subMonths(5),
            '3months' => $asOf->startOfMonth()->subMonths(2),
            'month' => $asOf->startOfMonth(),
            default => $asOf->startOfYear(),
        };

        $currentEnd = $asOf;
        $previousStart = $currentStart->subYear();
        $previousEnd = $currentEnd->subYear();

        if ($timeframe === 'year_over_year' || ! in_array($timeframe, [
            'last_12_months',
            '6months',
            '3months',
            'month',
        ], true)) {
            $timeframe = 'year_over_year';
        }

        return [
            'key' => $timeframe,
            'currentStart' => $currentStart,
            'currentEnd' => $currentEnd,
            'previousStart' => $previousStart,
            'previousEnd' => $previousEnd,
            'currentLabel' => $timeframe === 'year_over_year'
                ? $currentEnd->format('Y').' YTD'
                : $currentStart->format('M Y').'–'.$currentEnd->format('M Y'),
            'previousLabel' => $timeframe === 'year_over_year'
                ? $previousEnd->format('Y').' same period'
                : $previousStart->format('M Y').'–'.$previousEnd->format('M Y'),
        ];
    }

    private function monthsBetween(CarbonImmutable $start, CarbonImmutable $end): Collection
    {
        $months = collect();
        $month = $start->startOfMonth();
        $lastMonth = $end->startOfMonth();

        while ($month->lessThanOrEqualTo($lastMonth)) {
            $months->push($month);
            $month = $month->addMonth();
        }

        return $months;
    }

    private function monthlyTotals(
        CarbonImmutable $start,
        CarbonImmutable $end,
        string $locationId,
        string $productType,
        string $metric,
        array $plantLocations,
    ): Collection {
        $monthExpression = $this->monthExpression();

        return $this->lineQuery($start, $end, $locationId, $productType, $plantLocations)
            ->selectRaw("$monthExpression as month_key")
            ->selectRaw('SUM('.$this->metricExpression($metric).') as aggregate')
            ->groupByRaw($monthExpression)
            ->orderByRaw($monthExpression)
            ->get()
            ->mapWithKeys(fn (object $row): array => [
                $row->month_key => (float) $row->aggregate,
            ]);
    }

    private function breakdown(
        array $period,
        string $locationId,
        string $productType,
        string $metric,
        array $plantLocations,
    ): array {
        $current = $this->breakdownTotals(
            $period['currentStart'],
            $period['currentEnd'],
            $locationId,
            $productType,
            $metric,
            $plantLocations,
        );
        $previous = $this->breakdownTotals(
            $period['previousStart'],
            $period['previousEnd'],
            $locationId,
            $productType,
            $metric,
            $plantLocations,
        );

        $currentTotal = (float) $current->sum('value');

        return $current
            ->keys()
            ->merge($previous->keys())
            ->unique()
            ->map(function (string $key) use ($current, $previous, $currentTotal): array {
                $currentRow = $current->get($key);
                $previousRow = $previous->get($key);
                $currentValue = (float) ($currentRow['value'] ?? 0);
                $previousValue = (float) ($previousRow['value'] ?? 0);
                $change = $currentValue - $previousValue;

                return [
                    'key' => $key,
                    'code' => $currentRow['code'] ?? $previousRow['code'] ?? null,
                    'label' => $currentRow['label'] ?? $previousRow['label'] ?? $key,
                    'current' => $currentValue,
                    'previous' => $previousValue,
                    'change' => $change,
                    'changePercent' => $previousValue > 0 ? ($change / $previousValue) * 100 : null,
                    'share' => $currentTotal > 0 ? ($currentValue / $currentTotal) * 100 : 0,
                ];
            })
            ->sortByDesc('current')
            ->values()
            ->all();
    }

    private function breakdownTotals(
        CarbonImmutable $start,
        CarbonImmutable $end,
        string $locationId,
        string $productType,
        string $metric,
        array $plantLocations,
    ): Collection {
        $query = $this->lineQuery($start, $end, $locationId, $productType, $plantLocations)
            ->selectRaw('SUM('.$this->metricExpression($metric).') as aggregate');

        if ($productType === 'all') {
            $typeExpression = "COALESCE(products.product_type, 'Other')";

            return $query
                ->selectRaw("$typeExpression as product_group")
                ->groupByRaw($typeExpression)
                ->get()
                ->mapWithKeys(fn (object $row): array => [
                    $row->product_group => [
                        'code' => null,
                        'label' => $row->product_group,
                        'value' => (float) $row->aggregate,
                    ],
                ]);
        }

        return $query
            ->addSelect([
                'products.id as product_id',
                'products.sku',
                'products.name',
                'order_product.custom_description',
            ])
            ->groupBy([
                'products.id',
                'products.sku',
                'products.name',
                'order_product.custom_description',
            ])
            ->get()
            ->mapWithKeys(function (object $row): array {
                $code = filled($row->sku) ? $row->sku : 'CUSTOM';
                $label = filled($row->name)
                    ? $row->name
                    : ($row->custom_description ?: 'Custom product');
                $key = $row->product_id
                    ? 'product:'.$row->product_id
                    : 'custom:'.md5((string) $row->custom_description);

                return [
                    $key => [
                        'code' => $code,
                        'label' => $label,
                        'value' => (float) $row->aggregate,
                    ],
                ];
            });
    }

    private function lineQuery(
        CarbonImmutable $start,
        CarbonImmutable $end,
        string $locationId,
        string $productType,
        array $plantLocations,
    ): Builder {
        return DB::table('orders')
            ->join('order_product', 'orders.id', '=', 'order_product.order_id')
            ->leftJoin('products', 'order_product.product_id', '=', 'products.id')
            ->whereNull('orders.deleted_at')
            ->where('orders.status', '!=', OrderStatus::CANCELLED->value)
            ->whereNotNull('orders.order_date')
            ->whereBetween('orders.order_date', [
                $start->toDateString(),
                $end->toDateString(),
            ])
            ->whereIn('orders.plant_location', $plantLocations)
            ->when(
                $locationId !== 'all',
                fn (Builder $query): Builder => $query->where('orders.location_id', $locationId),
            )
            ->when(
                $productType !== 'all',
                fn (Builder $query): Builder => $query->whereRaw(
                    "COALESCE(products.product_type, 'Other') = ?",
                    [$productType],
                ),
            );
    }

    private function orderCount(
        CarbonImmutable $start,
        CarbonImmutable $end,
        string $locationId,
        string $productType,
        array $plantLocations,
    ): int {
        return $this->lineQuery($start, $end, $locationId, $productType, $plantLocations)
            ->distinct()
            ->count('orders.id');
    }

    private function completedVisitCount(
        CarbonImmutable $start,
        CarbonImmutable $end,
        string $locationId,
        array $plantLocations,
    ): int {
        $query = DB::table('sales_visits')
            ->join('locations', 'sales_visits.location_id', '=', 'locations.id')
            ->where('sales_visits.status', SalesVisitStatus::COMPLETED->value)
            ->whereNotNull('sales_visits.completed_at')
            ->whereBetween('sales_visits.completed_at', [$start, $end])
            ->when(
                $locationId !== 'all',
                fn (Builder $query): Builder => $query->where('sales_visits.location_id', $locationId),
            );

        if (count($plantLocations) < 3) {
            $query->whereIn('locations.default_plant_location', $plantLocations);
        }

        return $query->count();
    }

    private function dataThrough(
        CarbonImmutable $start,
        CarbonImmutable $end,
        string $locationId,
        string $productType,
        array $plantLocations,
    ): ?string {
        $date = $this->lineQuery($start, $end, $locationId, $productType, $plantLocations)
            ->max('orders.order_date');

        return $date
            ? CarbonImmutable::parse($date)->format('M j, Y')
            : null;
    }

    private function quantityExpression(): string
    {
        return 'CASE
            WHEN CAST(order_product.fill_load AS INTEGER) = 1
                THEN COALESCE(
                    order_product.quantity_delivered,
                    order_product.planned_fill_quantity,
                    order_product.quantity,
                    0
                )
            ELSE COALESCE(order_product.quantity, 0)
        END';
    }

    private function metricExpression(string $metric): string
    {
        $quantity = $this->quantityExpression();

        if ($metric === 'revenue') {
            return "($quantity) * COALESCE(order_product.price, products.price, 0)";
        }

        return $quantity;
    }

    private function monthExpression(): string
    {
        return match (DB::connection()->getDriverName()) {
            'pgsql' => "TO_CHAR(orders.order_date, 'YYYY-MM')",
            'mysql', 'mariadb' => "DATE_FORMAT(orders.order_date, '%Y-%m')",
            default => "strftime('%Y-%m', orders.order_date)",
        };
    }

    private function plantLocations(array $plants): array
    {
        $plants = array_values(array_intersect([
            'colma_main',
            'colma_locals',
            'tulare_plant',
        ], $plants));

        if ($plants === []) {
            return ['colma_main', 'colma_locals', 'tulare_plant'];
        }

        return $plants;
    }
}
