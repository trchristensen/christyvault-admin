<?php

namespace App\Filament\Sales\Pages;

use App\Models\Location;
use App\Models\Order;
use App\Models\SalesVisit;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Flowframe\Trend\Trend;
use Flowframe\Trend\TrendValue;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class SalesPerformance extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-presentation-chart-line';
    protected static ?string $navigationLabel = 'Sales Performance';
    protected static ?string $title = 'Sales Performance';
    protected static ?string $navigationGroup = 'Reports';
    protected static string $view = 'filament.sales.pages.sales-performance';

    public ?int $locationId = null;
    public ?string $timeframe = 'last_year';
    public array $chartData = [];
    public $salesResults;
    public $visitsResults;

    protected $listeners = ['updateChart' => 'loadChartData'];

    public function mount(): void
    {
        // Set Cedar Lawn as default location
        if (!$this->locationId) {
            $this->locationId = Location::where('name', 'like', '%Cedar Lawn%')->first()->id;
        }

        $this->form->fill([
            'locationId' => $this->locationId,
            'timeframe' => $this->timeframe ?? 'last_year'
        ]);

        logger()->info('Initial load with location:', ['locationId' => $this->locationId]);
        $this->loadChartData();
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Select::make('locationId')
                ->label('Location')
                ->options(Location::pluck('name', 'id'))
                ->live()
                ->afterStateUpdated(function ($state) {
                    logger()->info('Location changed', ['newValue' => $state]);
                    $this->locationId = $state;
                    $this->loadChartData();
                })
                ->required(),
            Select::make('timeframe')
                ->label('Time Period')
                ->options([
                    'last_year' => 'Last 12 Months',
                    'year' => 'This Year',
                    '6months' => 'Last 6 Months',
                    '3months' => 'Last 3 Months',
                    'month' => 'This Month',
                ])
                ->default('last_year')
                ->live()
                ->afterStateUpdated(function ($state) {
                    logger()->info('Timeframe changed to:', ['timeframe' => $state]);
                    $this->timeframe = $state;
                    $this->loadChartData();
                }),
        ])->columns(2);
    }

    public function loadChartData(): void
    {
        logger()->info('1. loadChartData called', [
            'locationId' => $this->locationId,
            'timeframe' => $this->timeframe
        ]);

        if (!$this->locationId) {
            logger()->info('2. No locationId, returning empty data');
            $this->chartData = [
                'datasets' => [],
                'labels' => [],
            ];
            $this->dispatch('chartDataUpdated', chartData: $this->chartData);
            return;
        }

        $start = match ($this->timeframe) {
            'last_year' => now()->subYear()->startOfMonth(),
            'year' => now()->startOfYear(),
            '6months' => now()->subMonths(6)->startOfMonth(),
            '3months' => now()->subMonths(3)->startOfMonth(),
            'month' => now()->startOfMonth(),
        };

        $end = now()->endOfMonth();

        // Get sales data
        $salesQuery = DB::table('orders')
            ->join('order_product', 'orders.id', '=', 'order_product.order_id')
            ->where('orders.location_id', $this->locationId)
            ->whereNull('orders.deleted_at')
            ->whereBetween('orders.created_at', [$start, $end])
            ->selectRaw("DATE_TRUNC('month', orders.created_at) as date")
            ->selectRaw('SUM(CASE
                WHEN order_product.fill_load = true THEN COALESCE(order_product.quantity_delivered, order_product.quantity)
                ELSE order_product.quantity
            END) as total_quantity')
            ->groupBy('date')
            ->orderBy('date');

        logger()->info('3. Sales query:', ['sql' => $salesQuery->toSql()]);
        $salesResults = $salesQuery->get();
        logger()->info('4. Sales results:', ['results' => $salesResults]);

        // Get visits data
        $visitsQuery = DB::table('sales_visits')
            ->where('location_id', $this->locationId)
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw("DATE_TRUNC('month', created_at) as date")
            ->selectRaw('COUNT(*) as total_visits')
            ->groupBy('date')
            ->orderBy('date');

        $visitsResults = $visitsQuery->get();

        // Get all unique dates
        $allDates = collect()
            ->merge($salesResults->pluck('date'))
            ->merge($visitsResults->pluck('date'))
            ->unique()
            ->sort();

        // Prepare data arrays with 0s for missing months
        $salesData = [];
        $visitsData = [];
        $labels = [];

        foreach ($allDates as $date) {
            $labels[] = Carbon::parse($date)->format('M Y');

            // Find sales for this date or use 0
            $salesData[] = $salesResults
                ->where('date', $date)
                ->first()
                ?->total_quantity ?? 0;

            // Find visits for this date or use 0
            $visitsData[] = $visitsResults
                ->where('date', $date)
                ->first()
                ?->total_visits ?? 0;
        }

        $chartData = [
            'datasets' => [
                [
                    'label' => 'Products Ordered',
                    'data' => $salesData,
                    'backgroundColor' => '#36A2EB',
                    'yAxisID' => 'y',
                    'borderRadius' => 4,
                ],
                [
                    'label' => 'Visits',
                    'data' => $visitsData,
                    'backgroundColor' => '#FF6384',
                    'yAxisID' => 'y1',
                    'borderRadius' => 4,
                ],
            ],
            'labels' => $labels,
        ];

        $this->chartData = $chartData;

        logger()->info('5. About to dispatch event with data:', [
            'chartData' => $chartData
        ]);

        $this->dispatch('chartDataUpdated', chartData: $chartData);

        logger()->info('6. Event dispatched');
    }

    public function refreshChart()
    {
        logger()->info('Manual chart refresh triggered');
        $this->loadChartData();
    }
}
