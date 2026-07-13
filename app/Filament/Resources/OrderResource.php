<?php

namespace App\Filament\Resources;

use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DatePicker;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use App\Filament\Resources\OrderResource\Pages\ListOrders;
use App\Filament\Resources\OrderResource\Pages\CreateOrder;
use App\Filament\Resources\OrderResource\Pages\EditOrder;
use App\Filament\Resources\OrderResource\Pages\DeliveryCalendar;
use App\Enums\OrderStatus;
use App\Enums\PlantLocation;
use App\Filament\Resources\OrderResource\Pages;
use App\Models\Employee;
use App\Models\Order;
use App\Models\Product;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Notifications\Notification;
use Closure;

use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Components\Tab;
use App\Filament\Resources\Traits\HasOrderForm;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Support\Facades\DB;
use Filament\Support\Colors\Color;


class OrderResource extends Resource
{
    use HasOrderForm;

    protected static ?string $model = Order::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static string | \UnitEnum | null $navigationGroup = 'Delivery Management';


    public static function form(Schema $schema): Schema
    {
        return $schema->components(static::getOrderFormSchema());
    }


    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('order_number')
                    ->label('Order #')
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->where('order_number', 'like', "%{$search}%");
                    })
                    ->sortable(),

                TextColumn::make('location.name')
                    ->searchable()
                    ->sortable()
                    ->description(fn(Order $record): string => $record->location->full_address ?? ''),


                TextColumn::make('requested_delivery_date')
                    ->label('Requested')
                    ->date('M j, Y')
                    ->sortable(),

                TextColumn::make('assigned_delivery_date')
                    ->label('Assigned')
                    ->date('M j, Y')
                    ->sortable(),
                TextColumn::make('driver.name')
                    ->label('Driver')
                    ->sortable()
                    ->default('Not assigned'),
                TextColumn::make('order_date')
                    ->label('Order Date')
                    ->date('M j, Y')
                    ->sortable(),
                TextColumn::make('status')
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

                // Tables\Columns\TextColumn::make('trip.trip_number')
                //     ->label('Trip')
                //     ->default('Unassigned'),

                TextColumn::make('products_summary')
                    ->label('Products')
                    ->state(fn (Order $record): string => implode("\n", static::getProductSummaryLines($record)))
                    ->formatStateUsing(fn (string $state): string => nl2br(e($state)))
                    ->html()
                    ->lineClamp(3)
                    ->tooltip(function (Order $record): ?string {
                        $lines = static::getProductSummaryLines($record);

                        return count($lines) > 3 ? implode(' • ', $lines) : null;
                    })
                    ->placeholder('—'),

            ])
            ->defaultGroup('delivery_group')
            ->groups([
                Group::make('delivery_group')
                    ->label('Delivery Schedule')

                    ->getKeyFromRecordUsing(function (Order $record): string {
                        $date = $record->assigned_delivery_date
                            ?? $record->requested_delivery_date
                            ?? $record->order_date;

                        if (! $date) {
                            return 'unscheduled';
                        }

                        $date = $date->copy()->startOfDay();
                        $today = now()->startOfDay();

                        if ($date->lt($today)) {
                            return 'past';
                        }

                        if ($date->lte($today->copy()->addWeeks(3))) {
                            return 'date:' . $date->format('Y-m-d');
                        }

                        return 'future';
                    })

                    ->getTitleFromRecordUsing(function (Order $record): string {
                        $date = $record->assigned_delivery_date
                            ?? $record->requested_delivery_date
                            ?? $record->order_date;

                        if (! $date) {
                            return 'Unscheduled Orders';
                        }

                        $date = $date->copy()->startOfDay();
                        $today = now()->startOfDay();

                        if ($date->lt($today)) {
                            return 'Past Orders';
                        }

                        if ($date->lte($today->copy()->addWeeks(3))) {
                            return $date->format('l, M j, Y');
                        }

                        return 'Future Orders (> 3 weeks)';
                    })

                    ->scopeQueryByKeyUsing(function (
                        Builder $query,
                        string $key,
                    ): Builder {
                        $dateExpression = '
                COALESCE(
                    assigned_delivery_date,
                    requested_delivery_date,
                    order_date
                )
            ';

                        if ($key === 'unscheduled') {
                            return $query->whereRaw(
                                "{$dateExpression} IS NULL"
                            );
                        }

                        if ($key === 'past') {
                            return $query->whereRaw(
                                "{$dateExpression} < CURRENT_DATE"
                            );
                        }

                        if ($key === 'future') {
                            return $query->whereRaw(
                                "{$dateExpression} > CURRENT_DATE + INTERVAL '3 weeks'"
                            );
                        }

                        if (str_starts_with($key, 'date:')) {
                            $date = substr($key, 5);

                            return $query->whereRaw(
                                "DATE({$dateExpression}) = ?",
                                [$date],
                            );
                        }

                        return $query->whereRaw('1 = 0');
                    })

                    ->orderQueryUsing(function (
                        Builder $query,
                        string $direction,
                    ): Builder {
                        return $query
                            ->orderByRaw('
                    CASE
                        WHEN COALESCE(
                            assigned_delivery_date,
                            requested_delivery_date,
                            order_date
                        ) >= CURRENT_DATE THEN 0

                        WHEN COALESCE(
                            assigned_delivery_date,
                            requested_delivery_date,
                            order_date
                        ) < CURRENT_DATE THEN 1

                        ELSE 2
                    END
                ')
                            ->orderByRaw(
                                'COALESCE(
                        assigned_delivery_date,
                        requested_delivery_date,
                        order_date
                    ) ' . $direction
                            );
                    })

                    ->collapsible(),


            ])
            ->filters([
                TrashedFilter::make(),
                SelectFilter::make('status')
                    ->options(OrderStatus::class)
                    ->multiple(),
                SelectFilter::make('plant_location')
                    ->label('Delivery Type')
                    ->options(
                        collect(PlantLocation::cases())
                            ->mapWithKeys(fn(PlantLocation $location) => [
                                $location->value => $location->getLabel(),
                            ])
                            ->toArray()
                    )
                    ->multiple(),
                Filter::make('product_notes')
                    ->schema([
                        TextInput::make('notes')
                            ->label('Product Notes')
                            ->placeholder('Search in product notes...'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['notes'],
                            fn(Builder $query, $notes): Builder => $query->whereHas('orderProducts', function ($query) use ($notes) {
                                $query->where('notes', 'like', "%{$notes}%");
                            })
                        );
                    }),
                Filter::make('product_location')
                    ->schema([
                        TextInput::make('location')
                            ->label('Product Location')
                            ->placeholder('Search in Product location...'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {

                        return $query->when(
                            $data['location'],
                            fn(Builder $query, $location): Builder => $query->whereHas('orderProducts', function ($query) use ($location) {
                                $query->whereRaw('LOWER(location) LIKE ?', ['%' . strtolower($location) . '%']);
                            })
                        );
                    }),

                Filter::make('requested_delivery_date')
                    ->schema([
                        DatePicker::make('from'),
                        DatePicker::make('until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'],
                                fn(Builder $query, $date): Builder => $query->whereDate('requested_delivery_date', '>=', $date),
                            )
                            ->when(
                                $data['until'],
                                fn(Builder $query, $date): Builder => $query->whereDate('requested_delivery_date', '<=', $date),
                            );
                    }),
                SelectFilter::make('product')
                    ->label('Has product(s)')
                    ->multiple()
                    ->options(Product::pluck('sku', 'id')->toArray())
                    ->query(function (Builder $query, array $data) {
                        if (!empty($data['values'])) {
                            foreach ($data['values'] as $productId) {
                                $query->whereHas('orderProducts', function ($q) use ($productId) {
                                    $q->where('product_id', $productId);
                                });
                            }
                        }
                        return $query;
                    }),
                SelectFilter::make('city')
                    ->label('City')
                    ->multiple()
                    ->options(function () {
                        return DB::table('locations')
                            ->join('orders', 'locations.id', '=', 'orders.location_id')
                            ->select(DB::raw("CONCAT(locations.city, ', ', locations.state) AS city_state"))
                            ->distinct()
                            ->orderBy('city_state')
                            ->pluck('city_state', 'city_state')
                            ->toArray();
                    })
                    ->query(function (Builder $query, array $data) {
                        if (!empty($data['values'])) {
                            $query->whereHas('location', function ($q) use ($data) {
                                $q->whereRaw("CONCAT(city, ', ', state) IN (" . implode(',', array_fill(0, count($data['values']), '?')) . ")", $data['values']);
                            });
                        }
                        return $query;
                    }),
                SelectFilter::make('state')
                    ->label('State')
                    ->multiple()
                    ->options(function () {
                        return DB::table('locations')
                            ->join('orders', 'locations.id', '=', 'orders.location_id')
                            ->whereNotNull('locations.state')
                            ->where('locations.state', '!=', '')
                            ->distinct()
                            ->orderBy('locations.state')
                            ->pluck('locations.state', 'locations.state')
                            ->toArray();
                    })
                    ->query(function (Builder $query, array $data) {
                        if (!empty($data['values'])) {
                            $query->whereHas('location', function ($q) use ($data) {
                                $q->whereIn('state', $data['values']);
                            });
                        }

                        return $query;
                    })

            ])
            ->recordActions([
                Action::make('view')
                    ->stickyModalFooter()
                    ->modalContent(fn($record) => view(
                        'filament.resources.order-resource.custom-view',
                        ['record' => $record]
                    ))
                    ->schema([])
                    ->modalFooterActions([
                        Action::make('edit')
                            ->modalWidth('7xl')
                            ->stickyModalFooter(),
                        Action::make('restore')
                            ->label('Restore')
                            ->icon('heroicon-o-arrow-uturn-left')
                            ->color('success')
                            ->action(fn(Order $record) => $record->restore())
                            ->requiresConfirmation()
                            ->visible(fn(Order $record) => $record->trashed()),
                        Action::make('duplicate')
                            ->label('Duplicate Order')
                            ->color(COLOR::Yellow)
                            ->icon('heroicon-o-document-duplicate')
                            ->url(fn(Order $record) => route('filament.admin.resources.orders.duplicate', ['record' => $record]))
                            ->openUrlInNewTab(),
                        Action::make('print')
                            ->label('Print Delivery Tag')
                            ->color(COLOR::Green)
                            ->icon('heroicon-o-printer')
                            ->url(fn(Order $record) => route('orders.print', ['order' => $record]))
                            ->openUrlInNewTab(),
                        Action::make('view-digital-tag')
                            ->label('Preview Delivery Tag')
                            ->color('gray')
                            ->icon('heroicon-o-printer')
                            ->url(fn(Order $record) => route('orders.print.formbg', ['order' => $record]))
                            ->openUrlInNewTab(),
                        DeleteAction::make(),
                    ]),
                ActionGroup::make([
                    Action::make('Print Delivery Tag')
                        ->label('Print Delivery Tag')
                        ->icon('heroicon-o-printer')
                        ->url(fn(Order $record): string => route('orders.print', $record))
                        ->openUrlInNewTab(),
                    EditAction::make(),
                    Action::make('duplicate')
                        ->label('Duplicate')
                        ->icon('heroicon-o-document-duplicate')
                        ->color('gray')
                        ->url(fn(Order $record): string => route('filament.admin.resources.orders.duplicate', ['record' => $record]))
                        ->openUrlInNewTab(),
                    Action::make('view-digital-tag')
                        ->label('Preview Delivery Tag')
                        ->color('gray')
                        ->icon('heroicon-o-printer')
                        ->url(fn(Order $record) => route('orders.print.formbg', ['order' => $record]))
                        ->openUrlInNewTab(),
                    DeleteAction::make(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOrders::route('/'),
            'create' => CreateOrder::route('/create'),
            'edit' => EditOrder::route('/{record}/edit'),
            'calendar' => DeliveryCalendar::route('/calendar'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with('orderProducts.product')
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    /**
     * Collapse repeated order-product rows into one readable line per product.
     *
     * @return array<int, string>
     */
    public static function getProductSummaryLines(Order $order): array
    {
        return $order->orderProducts
            ->groupBy(function ($orderProduct): string {
                $product = $orderProduct->is_custom_product
                    ? 'custom:' . mb_strtolower(trim($orderProduct->custom_description ?? 'Custom'))
                    : 'product:' . ($orderProduct->product_id ?? 'unknown');

                return $product . ':fill:' . (int) (bool) $orderProduct->fill_load;
            })
            ->map(function ($group): string {
                $orderProduct = $group->first();
                $label = $orderProduct->is_custom_product
                    ? ($orderProduct->custom_description ?? 'Custom')
                    : ($orderProduct->product?->sku ?? 'Unknown');

                if ($orderProduct->fill_load) {
                    return "Fill Load × {$label}";
                }

                return $group->sum('quantity') . " × {$label}";
            })
            ->values()
            ->all();
    }
}
