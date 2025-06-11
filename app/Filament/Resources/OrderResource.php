<?php

namespace App\Filament\Resources;

use App\Enums\OrderStatus;
use App\Filament\Resources\OrderResource\Pages;
use App\Models\Employee;
use App\Models\Order;
use App\Models\Product;
use Filament\Tables\Actions\Action;
use Filament\Forms;
use Filament\Forms\Form;
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

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationGroup = 'Delivery Management';


    public static function form(Form $form): Form
    {
        return $form->schema(static::getOrderFormSchema());
    }


    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('order_number')
                    ->label('Order #')
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->where('order_number', 'like', "%{$search}%");
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('location.name')
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
                Tables\Columns\TextColumn::make('driver.name')
                    ->label('Driver')
                    ->sortable()
                    ->default('Not assigned'),
                Tables\Columns\TextColumn::make('order_date')
                    ->label('Order Date')
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

                // Tables\Columns\TextColumn::make('trip.trip_number')
                //     ->label('Trip')
                //     ->default('Unassigned'),

                Tables\Columns\TextColumn::make('orderProducts')
                    ->label('Products')
                    ->formatStateUsing(function ($state, $record) {
                        $products = [];
                        foreach ($record->orderProducts as $orderProduct) {
                            $isCustom = $orderProduct->is_custom_product;
                            $key = ($isCustom ? 'custom-' . ($orderProduct->custom_description ?? $orderProduct->custom_name ?? $orderProduct->custom_sku ?? $orderProduct->id) : $orderProduct->product_id) . ($orderProduct->fill_load ? '-fill' : '');

                            if (!isset($products[$key])) {
                                if ($orderProduct->fill_load) {
                                    $sku = $isCustom
                                        ? ($orderProduct->custom_sku ?? $orderProduct->custom_name ?? 'Custom')
                                        : ($orderProduct->product->sku ?? 'Unknown');
                                    $products[$key] = "Fill Load x {$sku}";
                                } else {
                                    $quantity = $record->orderProducts
                                        ->where('product_id', $orderProduct->product_id)
                                        ->where('fill_load', false)
                                        ->sum('quantity');
                                    if ($isCustom) {
                                        $desc = $orderProduct->custom_description ?? $orderProduct->custom_name ?? $orderProduct->custom_sku ?? 'Custom';
                                        $products[$key] = "{$orderProduct->quantity} x {$desc}";
                                    } else {
                                        $sku = $orderProduct->product->sku ?? 'Unknown';
                                        $products[$key] = "{$quantity} x {$sku}";
                                    }
                                }
                            }
                        }
                        return nl2br(implode("\n", array_values($products)));
                    })
                    ->html(),

            ])
            ->defaultGroup('delivery_group')
            ->groups([
                Tables\Grouping\Group::make('delivery_group')
                    ->label('Delivery Schedule')
                    ->getTitleFromRecordUsing(function ($record) {
                        // Use assigned_delivery_date, then requested_delivery_date, then order_date
                        $date = $record->assigned_delivery_date ?? $record->requested_delivery_date ?? $record->order_date;
                        if (!$date) {
                            return 'Unscheduled Orders';
                        }
                        $today = now()->startOfDay();
                        $dateValue = $date->startOfDay();
                        if ($dateValue->lt($today)) {
                            return 'Past Orders';
                        }
                        if ($dateValue->lte($today->copy()->addWeeks(3))) {
                            return $dateValue->format('l, M j, Y');
                        }
                        return 'Future Orders (> 3 weeks)';
                    })
                    ->orderQueryUsing(function (Builder $query, string $direction) {
                        // Prioritize upcoming/future orders, put past orders last
                        return $query->orderByRaw('
                            CASE
                                WHEN COALESCE(assigned_delivery_date, requested_delivery_date, order_date) >= CURRENT_DATE THEN 0
                                WHEN COALESCE(assigned_delivery_date, requested_delivery_date, order_date) < CURRENT_DATE THEN 1
                                ELSE 2
                            END
                        ')
                        ->orderByRaw('COALESCE(assigned_delivery_date, requested_delivery_date, order_date) ' . $direction);
                    })
                    ->collapsible()
                    

            ])
            ->filters([
                Tables\Filters\TrashedFilter::make(),
                Tables\Filters\SelectFilter::make('status')
                    ->options(OrderStatus::class)
                    ->multiple(),
                Tables\Filters\Filter::make('product_notes')
                    ->form([
                        Forms\Components\TextInput::make('notes')
                            ->label('Product Notes')
                            ->placeholder('Search in product notes...'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['notes'],
                            fn (Builder $query, $notes): Builder => $query->whereHas('orderProducts', function ($query) use ($notes) {
                                $query->where('notes', 'like', "%{$notes}%");
                            })
                        );
                    }),
                    Tables\Filters\Filter::make('product_location')
                    ->form([
                        Forms\Components\TextInput::make('location')
                            ->label('Location')
                            ->placeholder('Search in location...'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['location'],
                            fn (Builder $query, $notes): Builder => $query->whereHas('orderProducts', function ($query) use ($notes) {
                                $query->where('location', 'like', "%{$notes}%");
                            })
                        );
                    }),
                Tables\Filters\Filter::make('requested_delivery_date')
                    ->form([
                        Forms\Components\DatePicker::make('from'),
                        Forms\Components\DatePicker::make('until'),
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
            ])
            ->actions([
                Action::make('view')
                    ->stickyModalFooter()
                    ->modalContent(fn($record) => view(
                        'filament.resources.order-resource.custom-view',
                        ['record' => $record]
                    ))
                    ->form([])
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
                        Tables\Actions\DeleteAction::make(),
                    ]),
                Tables\Actions\ActionGroup::make([
                    Action::make('Print Delivery Tag')
                        ->label('Print Delivery Tag')
                        ->icon('heroicon-o-printer')
                        ->url(fn(Order $record): string => route('orders.print', $record))
                        ->openUrlInNewTab(),
                    Tables\Actions\EditAction::make(),
                    Action::make('duplicate')
                        ->label('Duplicate')
                        ->icon('heroicon-o-document-duplicate')
                        ->color('gray')
                        ->url(fn (Order $record): string => route('filament.admin.resources.orders.duplicate', ['record' => $record]))
                        ->openUrlInNewTab(),
                      Action::make('view-digital-tag')
                            ->label('Preview Delivery Tag')
                            ->color('gray')
                            ->icon('heroicon-o-printer')
                            ->url(fn(Order $record) => route('orders.print.formbg', ['order' => $record]))
                            ->openUrlInNewTab(),
                    Tables\Actions\DeleteAction::make(),
                ]),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
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
            'index' => Pages\ListOrders::route('/'),
            'create' => Pages\CreateOrder::route('/create'),
            'edit' => Pages\EditOrder::route('/{record}/edit'),
            'calendar' => Pages\DeliveryCalendar::route('/calendar'),

        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
