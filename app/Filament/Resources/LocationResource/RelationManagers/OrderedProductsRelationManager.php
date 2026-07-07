<?php

namespace App\Filament\Resources\LocationResource\RelationManagers;

use Filament\Tables\Columns\TextColumn;
use Filament\Actions\Action;
use App\Filament\Resources\ProductResource;
use App\Models\Product;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;

class OrderedProductsRelationManager extends RelationManager
{
    protected static string $relationship = 'orders';

    protected static ?string $title = 'Ordered Products';

    protected function getTableQuery(): Builder | Relation | null
    {
        return Product::query()
            ->select([
                'products.id',
                'products.name',
                'products.sku',
                'products.unit',
                'products.product_type',
                'products.is_active',
            ])
            ->selectRaw('SUM(COALESCE(order_product.quantity, 0)) as total_quantity')
            ->selectRaw('COUNT(DISTINCT orders.id) as order_count')
            ->selectRaw('MAX(orders.order_date) as last_ordered_at')
            ->join('order_product', 'products.id', '=', 'order_product.product_id')
            ->join('orders', 'orders.id', '=', 'order_product.order_id')
            ->where('orders.location_id', $this->getOwnerRecord()->id)
            ->whereNull('orders.deleted_at')
            ->groupBy([
                'products.id',
                'products.name',
                'products.sku',
                'products.unit',
                'products.product_type',
                'products.is_active',
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('sku')
                    ->label('SKU')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->wrap(),
                TextColumn::make('product_type')
                    ->label('Type')
                    ->badge()
                    ->placeholder('Other')
                    ->sortable(),
                TextColumn::make('total_quantity')
                    ->label('Total Qty')
                    ->numeric()
                    ->sortable(query: fn(Builder $query, string $direction): Builder => $query->orderBy('total_quantity', $direction)),
                TextColumn::make('order_count')
                    ->label('Orders')
                    ->numeric()
                    ->sortable(query: fn(Builder $query, string $direction): Builder => $query->orderBy('order_count', $direction)),
                TextColumn::make('last_ordered_at')
                    ->label('Last Ordered')
                    ->date('M j, Y')
                    ->sortable(query: fn(Builder $query, string $direction): Builder => $query->orderBy('last_ordered_at', $direction)),
            ])
            ->defaultSort(fn(Builder $query): Builder => $query->orderByDesc('total_quantity'))
            ->emptyStateHeading('No products ordered')
            ->emptyStateDescription('This location does not have any product history yet.')
            ->recordActions([
                Action::make('open_product')
                    ->label('Open Product')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn(Product $record): string => ProductResource::getUrl('edit', ['record' => $record])),
            ]);
    }
}
