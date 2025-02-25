<?php

namespace App\Filament\Widgets;

use App\Models\Location;
use App\Models\Order;
use App\Models\SalesVisit;
use Filament\Forms\Components\Select;
use Filament\Widgets\ChartWidget;
use Flowframe\Trend\Trend;
use Flowframe\Trend\TrendValue;
use Illuminate\Support\Carbon;

class LocationSalesChart extends ChartWidget
{
    protected static ?string $heading = 'Location Performance';

    public ?string $filter = 'year';
    public ?int $locationId = null;

    protected function getFormSchema(): array
    {
        return [
            Select::make('locationId')
                ->label('Location')
                ->options(Location::pluck('name', 'id'))
                ->live()
                ->required(),
        ];
    }

    protected function getFiltersFormWidth(): string
    {
        return '2xl';
    }

    protected function getFilters(): ?array
    {
        return [
            'year' => 'This Year',
            '6months' => 'Last 6 Months',
            '3months' => 'Last 3 Months',
            'month' => 'This Month',
        ];
    }

    protected function getData(): array
    {
        if (!$this->locationId) {
            return [
                'datasets' => [],
                'labels' => [],
            ];
        }

        $activeFilter = $this->filter;

        $start = match ($activeFilter) {
            'year' => now()->startOfYear(),
            '6months' => now()->subMonths(6)->startOfMonth(),
            '3months' => now()->subMonths(3)->startOfMonth(),
            'month' => now()->startOfMonth(),
        };

        $end = now()->endOfMonth();

        // Get sales data
        $salesData = Trend::query(Order::where('location_id', $this->locationId))
            ->between(
                start: $start,
                end: $end,
            )
            ->perMonth()
            ->sum('total');

        // Get visits data
        $visitsData = Trend::query(SalesVisit::where('location_id', $this->locationId))
            ->between(
                start: $start,
                end: $end,
            )
            ->perMonth()
            ->count();

        return [
            'datasets' => [
                [
                    'label' => 'Sales ($)',
                    'data' => $salesData->map(fn(TrendValue $value) => $value->aggregate),
                    'borderColor' => '#36A2EB',
                    'yAxisID' => 'y',
                ],
                [
                    'label' => 'Visits',
                    'data' => $visitsData->map(fn(TrendValue $value) => $value->aggregate),
                    'borderColor' => '#FF6384',
                    'yAxisID' => 'y1',
                ],
            ],
            'labels' => $salesData->map(fn(TrendValue $value) => Carbon::parse($value->date)->format('M Y')),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getOptions(): array
    {
        return [
            'scales' => [
                'y' => [
                    'type' => 'linear',
                    'display' => true,
                    'position' => 'left',
                    'title' => [
                        'display' => true,
                        'text' => 'Sales ($)',
                    ],
                ],
                'y1' => [
                    'type' => 'linear',
                    'display' => true,
                    'position' => 'right',
                    'title' => [
                        'display' => true,
                        'text' => 'Number of Visits',
                    ],
                    'grid' => [
                        'drawOnChartArea' => false,
                    ],
                ],
            ],
        ];
    }
}
