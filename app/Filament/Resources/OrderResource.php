<?php

namespace App\Filament\Resources;

use App\Enums\OrderStatus;
use App\Filament\Resources\OrderResource\Pages;
use App\Models\Customer;
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
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('customer.name')
                    ->searchable()
                    ->sortable()
                    ->description(fn(Order $record): string => $record->location->full_address),


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
                                    $products[$key] = "Fill Load x {$orderProduct->product->name}";
                                } else {
                                    $quantity = $record->orderProducts
                                        ->where('product_id', $orderProduct->product_id)
                                        ->where('fill_load', false)
                                        ->sum('quantity');
                                    $products[$key] = "{$quantity} x {$orderProduct->product->name}";
                                }
                            }
                        }

                        return nl2br(implode("\n", array_values($products)));
                    })
                    ->html(),

            ])
            ->defaultGroup('assigned_delivery_date')
            ->groups([
                Tables\Grouping\Group::make('assigned_delivery_date')
                    ->label('Delivery Date')
                    ->date()
                    ->collapsible()
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make(),
                Tables\Filters\SelectFilter::make('status')
                    ->options(OrderStatus::class)
                    ->multiple(),
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
                Action::make('print preview')
                    ->label(null)
                    ->iconButton()
                    ->icon('heroicon-o-printer')
                    ->url(fn(Order $record): string => route('orders.print', $record))
                    ->openUrlInNewTab(),
                Tables\Actions\EditAction::make(),
                // Action::make('mark_delivered')
                //     ->label('Mark Delivered')
                //     ->icon('heroicon-o-truck')
                //     ->color('success')
                //     ->action(fn(Order $record) => $record->update(['status' => 'delivered']))
                //     ->requiresConfirmation()
                //     ->hidden(fn(Order $record) => $record->status === 'delivered'),
                // Action::make('cancel_order')
                //     ->label('Cancel Order')
                //     ->icon('heroicon-o-x-circle')
                //     ->color('danger')
                //     ->action(fn(Order $record) => $record->update(['status' => 'cancelled']))
                //     ->requiresConfirmation()
                //     ->hidden(fn(Order $record) => in_array($record->status, ['delivered', 'cancelled'])),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
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
