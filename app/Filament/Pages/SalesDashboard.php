<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Support\Facades\FilamentIcon;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;

class SalesDashboard extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-presentation-chart-line';
    protected static ?string $navigationLabel = 'Sales Dashboard';
    protected static ?string $title = 'Sales Dashboard';
    protected static ?int $navigationSort = 2;
    
    protected static string $view = 'filament.pages.sales-dashboard';
} 