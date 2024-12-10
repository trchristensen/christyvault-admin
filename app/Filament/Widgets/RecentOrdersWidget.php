<?php

namespace App\Filament\Widgets;

use App\Enums\OrderStatus;
use App\Models\Order;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Filament\Tables\Actions\Action;

class RecentOrdersWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Order::query()->latest()
            )
            ->columns([
                Tables\Columns\TextColumn::make('order_number')
                    ->label('Order #')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('customer.name')
                    ->searchable()
                    ->sortable()
                    ->description(fn(Order $record): string => $record->location->full_address ?? ''),

                Tables\Columns\TextColumn::make('requested_delivery_date')
                    ->label('Requested')
                    ->date('M j, Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('assigned_delivery_date')
                    ->label('Assigned')
                    ->date('M j, Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->sortable()
                    ->formatStateUsing(fn($state): string => ucfirst(str_replace('_', ' ', (string) $state)))
                    ->color(fn($state): string => match ((string) $state) {
                        'cancelled' => 'danger',
                        'pending' => 'warning',
                        'confirmed' => 'info',
                        'in_production' => 'purple',
                        'ready_for_delivery' => 'success',
                        'out_for_delivery' => 'orange',
                        'delivered' => 'success',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('trip.trip_number')
                    ->label('Trip')
                    ->default('Unassigned'),

                Tables\Columns\TextColumn::make('orderProducts')
                    ->label('Products')
                    ->formatStateUsing(function ($state, $record) {
                        $products = [];

                        foreach ($record->orderProducts as $orderProduct) {
                            $key = $orderProduct->product_id . ($orderProduct->fill_load ? '-fill' : '');

                            if (!isset($products[$key])) {
                                if ($orderProduct->fill_load) {
                                    $products[$key] = "Fill Load x {$orderProduct->product->sku}";
                                } else {
                                    $quantity = $record->orderProducts
                                        ->where('product_id', $orderProduct->product_id)
                                        ->where('fill_load', false)
                                        ->sum('quantity');
                                    $products[$key] = "{$quantity} x {$orderProduct->product->sku}";
                                }
                            }
                        }

                        return nl2br(implode("\n", array_values($products)));
                    })
                    ->html(),
            ])
            ->actions([
                Action::make('print preview')
                    ->label(null)
                    ->iconButton()
                    ->icon('heroicon-o-printer')
                    ->url(fn(Order $record): string => route('orders.print', $record))
                    ->openUrlInNewTab(),
                Tables\Actions\EditAction::make(),
            ]);
    }
}
