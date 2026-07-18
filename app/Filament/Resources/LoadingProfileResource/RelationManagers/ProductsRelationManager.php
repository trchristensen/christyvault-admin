<?php

namespace App\Filament\Resources\LoadingProfileResource\RelationManagers;

use App\Filament\Resources\ProductResource;
use App\Models\Product;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ProductsRelationManager extends RelationManager
{
    protected static string $relationship = 'products';

    protected static ?string $title = 'Products Using This Profile';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('sku')
                    ->label('Product Number')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->wrap(),
                TextColumn::make('product_type')
                    ->label('Product Type')
                    ->badge()
                    ->placeholder('Other')
                    ->sortable(),
                TextColumn::make('weight_lbs')
                    ->label('Weight')
                    ->formatStateUsing(fn ($state): string => filled($state) ? number_format((float) $state, 2).' lb' : 'Unknown')
                    ->sortable(),
                TextColumn::make('orders_count')
                    ->label('Orders')
                    ->counts('orders')
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
            ])
            ->defaultSort('sku')
            ->emptyStateHeading('No products use this loading profile')
            ->recordActions([
                Action::make('open_product')
                    ->label('Open Product')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (Product $record): string => ProductResource::getUrl('edit', ['record' => $record])),
            ]);
    }
}
