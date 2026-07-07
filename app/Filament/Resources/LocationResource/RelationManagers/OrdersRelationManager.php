<?php

namespace App\Filament\Resources\LocationResource\RelationManagers;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Actions\Action;
use App\Enums\OrderStatus;
use App\Enums\PlantLocation;
use App\Filament\Resources\OrderResource;
use App\Models\Order;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class OrdersRelationManager extends RelationManager
{
    protected static string $relationship = 'orders';

    protected static ?string $title = 'Orders';

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn(Builder $query): Builder => $query
                ->with([
                    'driver',
                    'location.plantDriveDistanceOrigin',
                    'location.preferredDeliveryContact',
                    'orderProducts.product',
                ])
                ->withCount('orderProducts'))
            ->columns([
                TextColumn::make('order_number')
                    ->label('Order #')
                    ->searchable()
                    ->sortable()
                    ->description(fn(Order $record): ?string => $record->customer_order_number
                        ? "Customer # {$record->customer_order_number}"
                        : null),

                TextColumn::make('assigned_delivery_date')
                    ->label('Assigned')
                    ->date('M j, Y')
                    ->sortable()
                    ->description(fn(Order $record): ?string => $record->requested_delivery_date
                        ? 'Requested ' . $record->requested_delivery_date->format('M j, Y')
                        : null),

                TextColumn::make('order_date')
                    ->label('Ordered')
                    ->date('M j, Y')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('plant_location')
                    ->label('Delivery Type')
                    ->badge()
                    ->formatStateUsing(fn($state): string => PlantLocation::tryFrom((string) $state)?->getLabel() ?? 'N/A')
                    ->sortable(),

                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn($state): string => OrderStatus::tryFrom((string) $state)?->label() ?? str((string) $state)->replace('_', ' ')->title())
                    ->color(fn($state): string => match ((string) $state) {
                        OrderStatus::CANCELLED->value => 'danger',
                        OrderStatus::PENDING->value,
                        OrderStatus::WILL_CALL->value,
                        OrderStatus::PICKED_UP->value,
                        OrderStatus::TRANSFER->value,
                        OrderStatus::TRANSFERRED->value => 'warning',
                        OrderStatus::CONFIRMED->value,
                        OrderStatus::INVOICED->value => 'info',
                        OrderStatus::IN_PRODUCTION->value => 'purple',
                        OrderStatus::PREBURY->value,
                        OrderStatus::READY_FOR_DELIVERY->value,
                        OrderStatus::OUT_FOR_DELIVERY->value,
                        OrderStatus::ARRIVED->value,
                        OrderStatus::DELIVERED->value,
                        OrderStatus::COMPLETED->value,
                        OrderStatus::PREBURY_DELIVERED->value => 'success',
                        default => 'gray',
                    })
                    ->sortable(),

                IconColumn::make('is_printed')
                    ->label('Printed')
                    ->boolean()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('order_products_count')
                    ->label('Lines')
                    ->numeric()
                    ->sortable()
                    ->alignCenter()
                    ->toggleable(),

                TextColumn::make('orderProducts')
                    ->label('Products')
                    ->formatStateUsing(fn($state, Order $record): string => self::summarizeOrderProducts($record))
                    ->html()
                    ->wrap()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(OrderStatus::toArray())
                    ->multiple(),

                SelectFilter::make('plant_location')
                    ->label('Delivery Type')
                    ->options(
                        collect(PlantLocation::cases())
                            ->mapWithKeys(fn(PlantLocation $location): array => [
                                $location->value => $location->getLabel(),
                            ])
                            ->toArray()
                    )
                    ->multiple(),
            ])
            ->defaultSort('order_date', 'desc')
            ->emptyStateHeading('No orders yet')
            ->emptyStateDescription('Orders for this location will show up here.')
            ->recordActions([
                Action::make('view')
                    ->label('View')
                    ->icon('heroicon-o-eye')
                    ->modalWidth('7xl')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->modalHeading(fn(Order $record): string => "View {$record->order_number}")
                    ->modalContent(fn(Order $record) => view(
                        'filament.resources.order-resource.custom-view',
                        [
                            'record' => $record->loadMissing([
                                'location.plantDriveDistanceOrigin',
                                'location.preferredDeliveryContact',
                                'orderProducts.product',
                            ]),
                        ]
                    )),

                Action::make('edit')
                    ->label('Edit')
                    ->icon('heroicon-o-pencil-square')
                    ->url(fn(Order $record): string => OrderResource::getUrl('edit', ['record' => $record])),

                Action::make('print')
                    ->label('Print Tag')
                    ->icon('heroicon-o-printer')
                    ->url(fn(Order $record): string => route('orders.print', ['order' => $record]))
                    ->openUrlInNewTab(),
            ])
            ->toolbarActions([]);
    }

    private static function summarizeOrderProducts(Order $record): string
    {
        $products = [];

        foreach ($record->orderProducts as $orderProduct) {
            $isCustom = $orderProduct->is_custom_product;
            $key = ($isCustom ? 'custom-' . ($orderProduct->custom_description ?? $orderProduct->id) : $orderProduct->product_id)
                . ($orderProduct->fill_load ? '-fill' : '');

            if (isset($products[$key])) {
                continue;
            }

            if ($orderProduct->fill_load) {
                $sku = $isCustom
                    ? ($orderProduct->custom_description ?? 'Custom')
                    : ($orderProduct->product->sku ?? 'Unknown');

                $products[$key] = "Fill Load x {$sku}";

                continue;
            }

            if ($isCustom) {
                $products[$key] = "{$orderProduct->quantity} x " . ($orderProduct->custom_description ?? 'Custom');

                continue;
            }

            $quantity = $record->orderProducts
                ->where('product_id', $orderProduct->product_id)
                ->where('fill_load', false)
                ->sum('quantity');

            $products[$key] = "{$quantity} x " . ($orderProduct->product->sku ?? 'Unknown');
        }

        return nl2br(e(collect($products)->take(5)->join("\n")));
    }
}
