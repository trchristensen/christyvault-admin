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
use Filament\Forms\Components\Forms;

class SalesPerformance extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-presentation-chart-line';
    protected static ?string $navigationLabel = 'Sales Performance';
    protected static ?string $title = 'Sales Performance';
    protected static ?string $navigationGroup = 'Reports';
    protected static string $view = 'filament.sales.pages.sales-performance';

    public ?string $locationId = 'all';
    public ?string $timeframe = 'last_year';
    public array $chartData = [];
    public $salesResults;
    public $visitsResults;

    protected $listeners = ['updateChart' => 'loadChartData'];

    public function mount(): void
    {
        $this->form->fill([
            'locationId' => $this->locationId,
            'timeframe' => $this->timeframe ?? 'last_year'
        ]);

        logger()->info('Initial load with location:', ['locationId' => $this->locationId]);
        $this->loadChartData();
    }

    public function form(Form $form): Form
    {
        // Get locations ordered by total orders
        $locationsByOrders = Location::select('locations.*')
            ->leftJoin('orders', 'locations.id', '=', 'orders.location_id')
            ->leftJoin('order_product', 'orders.id', '=', 'order_product.order_id')
            ->whereNull('orders.deleted_at')
            ->groupBy('locations.id')
            ->orderByRaw('COALESCE(SUM(order_product.quantity), 0) DESC')
            ->get();

        // Create options array with string keys
        $locationOptions = [
            'all' => 'All Locations'
        ];

        foreach ($locationsByOrders as $location) {
            $locationOptions[(string)$location->id] = trim($location->name);
        }

        logger()->info('Location options created:', [
            'options' => $locationOptions,
            'firstFewLocations' => array_slice($locationOptions, 0, 5, true)
        ]);

        return $form->schema([
            Select::make('locationId')
                ->label('Location')
                ->options($locationOptions)
                ->default('all')
                ->live()
                ->afterStateUpdated(function ($state) {
                    logger()->info('Location selection:', [
                        'newValue' => $state,
                        'type' => gettype($state),
                        'locationId' => $this->locationId
                    ]);
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

    public function updatedLocationId($value)
    {
        logger()->info('Location changed', ['newValue' => $value]);

        $this->loadChartData();

        // Make sure we have data before dispatching
        if (empty($this->chartData['datasets'])) {
            logger()->warning('No datasets available for dispatch');
            return;
        }

        logger()->info('About to dispatch event with data:', [
            'chartData' => $this->chartData
        ]);

        // Explicitly structure the event data
        $this->dispatch('chartDataUpdated', chartData: [
            'datasets' => $this->chartData['datasets'],
            'labels' => $this->chartData['labels']
        ]);
    }

    private function loadChartData()
    {
        $start = now()->subYear()->startOfMonth();
        $end = now()->endOfMonth();

        if ($this->locationId !== 'all') {
            // Debug the location ID before querying
            logger()->info('Processing location:', [
                'locationId' => $this->locationId,
                'type' => gettype($this->locationId)
            ]);

            // Check if location exists
            $location = Location::find($this->locationId);

            if (!$location) {
                logger()->error('Location not found:', [
                    'requestedId' => $this->locationId
                ]);
                return;
            }

            // Check for orders
            $orders = Order::where('location_id', $this->locationId)
                ->whereNull('deleted_at')
                ->whereBetween('created_at', [$start, $end])
                ->get();

            logger()->info('Orders found:', [
                'locationId' => $this->locationId,
                'orderCount' => $orders->count(),
                'sampleOrders' => $orders->take(3)->toArray()
            ]);

            if ($orders->isEmpty()) {
                logger()->info('No orders found for location in date range');
                return;
            }
        }

        // Main query
        $salesQuery = DB::table('orders')
            ->join('order_product', 'orders.id', '=', 'order_product.order_id')
            ->join('products', 'order_product.product_id', '=', 'products.id')
            ->whereNull('orders.deleted_at')
            ->whereBetween('orders.created_at', [$start, $end]);

        if ($this->locationId !== 'all') {
            $salesQuery->where('orders.location_id', $this->locationId);
        }

        $salesQuery->selectRaw("DATE_TRUNC('month', orders.created_at) as date")
            ->selectRaw("COALESCE(products.product_type, 'Other') as product_type")
            ->selectRaw('SUM(CASE
                WHEN order_product.fill_load = 1 THEN COALESCE(order_product.quantity_delivered, order_product.quantity)
                ELSE order_product.quantity
            END) as total_quantity')
            ->groupBy('date', DB::raw("COALESCE(products.product_type, 'Other')"))
            ->orderBy('date');

        $salesResults = $salesQuery->get();
        logger()->info('Final query results:', [
            'locationId' => $this->locationId,
            'sql' => $salesQuery->toSql(),
            'bindings' => $salesQuery->getBindings(),
            'results' => $salesResults
        ]);

        // Get visits data with same 'all' handling
        $visitsQuery = DB::table('sales_visits')
            ->whereBetween('created_at', [$start, $end]);

        // Only filter by location if not 'all'
        if ($this->locationId !== 'all') {
            $visitsQuery->where('location_id', $this->locationId);
        }

        $visitsQuery->selectRaw("DATE_TRUNC('month', created_at) as date")
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

        // Transform the data for the chart
        $dates = $salesResults->pluck('date')->unique()->sort()->values();
        $productTypes = $salesResults->pluck('product_type')->unique()->values();

        // Define a neutral, distinct color palette
        $colors = [
            'Wilbert Burial Vaults' => '#4A6FA5',  // Muted blue
            'Wilbert Urn Vaults' => '#98A6B5',     // Cool gray
            'Outer Burial Containers' => '#6B4E3D', // Warm brown
            'Other' => '#8B8589',                  // Neutral gray
            'Visits' => '#3333'                  // Warm beige
        ];

        // First create product datasets
        $productDatasets = [];
        foreach ($productTypes as $type) {
            $data = [];
            foreach ($dates as $date) {
                $quantity = $salesResults
                    ->where('date', $date)
                    ->where('product_type', $type)
                    ->sum('total_quantity');
                $data[] = $quantity;
            }

            $productDatasets[] = [
                'label' => $type,
                'data' => $data,
                'backgroundColor' => $colors[$type] ?? '#' . substr(md5($type), 0, 6),
                'yAxisID' => 'y',
                'stack' => 'products',
                'borderRadius' => 4,
            ];
        }

        // Add visits dataset separately
        $visitsDataset = [
            'label' => 'Visits',
            'data' => array_values($visitsData),
            'backgroundColor' => $colors['Visits'],
            'yAxisID' => 'y1',
            'stack' => 'visits',
            'borderRadius' => 4,
        ];

        // Ensure proper data structure
        $chartData = [
            'datasets' => array_values(array_merge($productDatasets, [$visitsDataset])), // Ensure numeric array
            'labels' => $dates->map(fn($date) => Carbon::parse($date)->format('M Y'))->values()->toArray(),
        ];

        // Verify data structure before assignment
        if (!empty($chartData['datasets'])) {
            $this->chartData = $chartData;
            logger()->info('Chart data loaded successfully', [
                'datasets' => count($chartData['datasets']),
                'labels' => count($chartData['labels'])
            ]);
        } else {
            logger()->warning('No datasets generated in loadChartData');
            $this->chartData = [
                'datasets' => [],
                'labels' => []
            ];
        }

        $this->dispatch('chartDataUpdated', data: $this->chartData);
    }

    public function refreshChart()
    {
        logger()->info('Manual chart refresh triggered');
        $this->loadChartData();
    }
}
