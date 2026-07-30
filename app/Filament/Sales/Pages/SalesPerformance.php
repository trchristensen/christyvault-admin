<?php

namespace App\Filament\Sales\Pages;

use App\Models\Location;
use App\Models\Product;
use App\Services\SalesPerformanceReport;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Schemas\Schema;

class SalesPerformance extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-presentation-chart-line';

    protected static ?string $navigationLabel = 'Sales Performance';

    protected static ?string $title = 'Sales Performance';

    protected static string|\UnitEnum|null $navigationGroup = 'Reports';

    protected string $view = 'filament.sales.pages.sales-performance';

    public ?string $locationId = 'all';

    public array $plants = ['colma', 'tulare'];

    public ?string $timeframe = 'year_over_year';

    public ?string $metric = 'units';

    public ?string $productType = 'all';

    public array $chartData = [];

    public array $report = [];

    public function mount(): void
    {
        $this->form->fill([
            'locationId' => $this->locationId,
            'plants' => $this->plants,
            'timeframe' => $this->timeframe,
            'metric' => $this->metric,
            'productType' => $this->productType,
        ]);

        $this->loadReport();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('locationId')
                    ->label('Location')
                    ->options(fn (): array => [
                        'all' => 'All Locations',
                        ...Location::query()
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->mapWithKeys(fn (string $name, int $id): array => [(string) $id => trim($name)])
                            ->all(),
                    ])
                    ->searchable()
                    ->live()
                    ->afterStateUpdated(function ($state): void {
                        $this->locationId = (string) $state;
                        $this->loadReport();
                    })
                    ->required(),
                Select::make('plants')
                    ->label('Plant')
                    ->options([
                        'colma' => 'Colma',
                        'tulare' => 'Tulare',
                    ])
                    ->multiple()
                    ->minItems(1)
                    ->live()
                    ->afterStateUpdated(function ($state): void {
                        $this->plants = array_values((array) $state);
                        $this->loadReport();
                    })
                    ->required(),
                Select::make('timeframe')
                    ->label('Comparison Period')
                    ->options([
                        'year_over_year' => 'This Year vs Last Year',
                        'last_12_months' => 'Last 12 Months vs Prior Year',
                        '6months' => 'Last 6 Months vs Prior Year',
                        '3months' => 'Last 3 Months vs Prior Year',
                        'month' => 'This Month vs Last Year',
                    ])
                    ->live()
                    ->afterStateUpdated(function ($state): void {
                        $this->timeframe = (string) $state;
                        $this->loadReport();
                    })
                    ->required(),
                Select::make('metric')
                    ->label('Measure')
                    ->options([
                        'units' => 'Units Ordered',
                        'revenue' => 'Order Revenue',
                    ])
                    ->live()
                    ->afterStateUpdated(function ($state): void {
                        $this->metric = (string) $state;
                        $this->loadReport();
                    })
                    ->required(),
                Select::make('productType')
                    ->label('Product Detail')
                    ->options(fn (): array => $this->productTypeOptions())
                    ->searchable()
                    ->live()
                    ->afterStateUpdated(function ($state): void {
                        $this->productType = (string) $state;
                        $this->loadReport();
                    })
                    ->required(),
            ])
            ->columns([
                'default' => 1,
                'md' => 2,
                'xl' => 5,
            ]);
    }

    public function drillIntoCategory(string $productType): void
    {
        if (! array_key_exists($productType, $this->productTypeOptions())) {
            return;
        }

        $this->productType = $productType;
        $this->form->fill([
            'locationId' => $this->locationId,
            'plants' => $this->plants,
            'timeframe' => $this->timeframe,
            'metric' => $this->metric,
            'productType' => $this->productType,
        ]);
        $this->loadReport();
    }

    public function drillIntoCategoryAt(int $rowIndex): void
    {
        $row = $this->report['breakdown']['rows'][$rowIndex] ?? null;

        if (! is_array($row) || ! is_string($row['key'] ?? null)) {
            return;
        }

        $this->drillIntoCategory($row['key']);
    }

    public function clearProductDrilldown(): void
    {
        $this->productType = 'all';
        $this->form->fill([
            'locationId' => $this->locationId,
            'plants' => $this->plants,
            'timeframe' => $this->timeframe,
            'metric' => $this->metric,
            'productType' => $this->productType,
        ]);
        $this->loadReport();
    }

    public function formatMetricValue(float|int|null $value, bool $signed = false): string
    {
        $value = (float) ($value ?? 0);
        $prefix = $signed && $value > 0 ? '+' : '';

        if ($this->metric === 'revenue') {
            return $prefix.'$'.number_format($value, 0);
        }

        return $prefix.number_format($value, 0);
    }

    public function formatPercent(float|int|null $value, bool $signed = false): string
    {
        if ($value === null) {
            return '—';
        }

        $value = (float) $value;
        $prefix = $signed && $value > 0 ? '+' : '';

        return $prefix.number_format($value, 1).'%';
    }

    public function loadReport(): void
    {
        $this->report = app(SalesPerformanceReport::class)->build(
            locationId: $this->locationId ?? 'all',
            timeframe: $this->timeframe ?? 'year_over_year',
            metric: $this->metric ?? 'units',
            productType: $this->productType ?? 'all',
            plants: $this->plants,
        );
        $this->chartData = $this->report['chart'];

        $this->dispatch(
            'sales-performance-updated',
            chartData: $this->chartData,
            chartMeta: $this->report['chartMeta'],
        );
    }

    private function productTypeOptions(): array
    {
        $types = Product::query()
            ->whereNotNull('product_type')
            ->where('product_type', '!=', '')
            ->distinct()
            ->orderBy('product_type')
            ->pluck('product_type')
            ->mapWithKeys(fn (string $type): array => [$type => $type])
            ->all();

        if (! array_key_exists('Other', $types)) {
            $types['Other'] = 'Other';
        }

        return [
            'all' => 'All Product Categories',
            ...$types,
        ];
    }
}
