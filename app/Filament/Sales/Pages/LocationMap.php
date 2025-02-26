<?php

namespace App\Filament\Sales\Pages;

use App\Models\Location;
use App\Models\Order;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;

class LocationMap extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-map';
    protected static ?string $navigationLabel = 'Location Map';
    protected static ?string $title = 'Location Map';
    protected static ?string $navigationGroup = 'Reports';
    protected static string $view = 'filament.sales.pages.location-map';

    public ?string $timeframe = 'last_year';
    public array $mapData = [];

    public function mount(): void
    {
        $this->form->fill([
            'timeframe' => $this->timeframe ?? 'last_year'
        ]);

        $this->loadMapData();
    }

    public function form(Form $form): Form
    {
        return $form->schema([
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
                    $this->timeframe = $state;
                    $this->loadMapData();
                }),
        ])->columns(1);
    }

    private function loadMapData(): void
    {
        $start = now()->subYear()->startOfMonth();
        $end = now()->endOfMonth();

        // Get location data with order counts
        $query = Location::select('locations.*')
            ->selectRaw('COALESCE(SUM(CASE
                WHEN order_product.fill_load = true
                THEN COALESCE(order_product.quantity_delivered, order_product.quantity)
                ELSE order_product.quantity
            END), 0) as total_products')
            ->leftJoin('orders', 'locations.id', '=', 'orders.location_id')
            ->leftJoin('order_product', 'orders.id', '=', 'order_product.order_id')
            ->whereNull('orders.deleted_at')
            ->whereBetween('orders.created_at', [$start, $end])
            ->groupBy('locations.id', 'locations.name', 'locations.latitude', 'locations.longitude')
            ->havingRaw('COALESCE(SUM(CASE
                WHEN order_product.fill_load = true
                THEN COALESCE(order_product.quantity_delivered, order_product.quantity)
                ELSE order_product.quantity
            END), 0) > 0');

        logger()->info('Location query:', [
            'sql' => $query->toSql(),
            'bindings' => $query->getBindings()
        ]);

        $locationData = $query->get();

        $this->mapData = $locationData->map(function ($location) {
            return [
                'id' => $location->id,
                'name' => $location->name,
                'lat' => (float)$location->latitude,
                'lng' => (float)$location->longitude,
                'total_products' => (int)$location->total_products,
            ];
        })->toArray();

        logger()->info('Map data loaded:', [
            'locationCount' => count($this->mapData),
            'sample' => array_slice($this->mapData, 0, 3)
        ]);
    }
}
