<?php

namespace App\Filament\Operations\Widgets;

use App\Models\PurchaseOrder;
use Filament\Tables\Columns\TextColumn;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class RecentPurchaseOrdersWidget extends TableWidget
{
    protected static ?int $sort = 3;
    protected int $defaultPaginationPageOption = 5;

    protected function getTableQuery(): Builder
    {
        return PurchaseOrder::query()
            ->latest()
            ->where('status', '!=', 'received')
            ->take(5);
    }

    protected function getTableColumns(): array
    {
        return [
            TextColumn::make('supplier.name')
                ->searchable(),
            TextColumn::make('status')
                ->badge()
                ->color(fn (string $state): string => match ($state) {
                    'draft' => 'gray',
                    'submitted' => 'warning',
                    'cancelled' => 'danger',
                    default => 'primary',
                }),
            TextColumn::make('total_amount')
                ->money('USD'),
            TextColumn::make('expected_delivery_date')
                ->date()
                ->sortable(),
        ];
    }

    protected function isTablePaginationEnabled(): bool
    {
        return false;
    }
} 