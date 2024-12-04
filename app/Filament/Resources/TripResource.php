<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TripResource\Pages;
use App\Filament\Resources\TripResource\RelationManagers;
use App\Models\Driver;
use App\Models\Trip;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use App\Models\Order;
use App\Models\Employee;
use Filament\Notifications\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use App\Filament\Resources\Traits\HasTripForm;

class TripResource extends Resource
{
    use HasTripForm;

    protected static ?string $model = Trip::class;

    protected static ?string $navigationIcon = 'heroicon-o-truck';
    protected static ?string $navigationGroup = 'Delivery Management';

    public static function form(Form $form): Form
    {
        return $form
            ->schema(static::getTripFormSchema());
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('trip_number')
                    ->searchable(),
                Tables\Columns\TextColumn::make('driver.name')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('delivery_details')
                    ->label('Delivery Details')
                    ->html()
                    ->state(function (Trip $record): string {
                        $orders = $record->orders()->orderBy('stop_number')->get();
                        $ordersHtml = '';
                        $totalStops = $orders->count();

                        foreach ($orders as $order) {
                            $productsHtml = '';
                            foreach ($order->orderProducts as $orderProduct) {
                                $quantity = $orderProduct->fill_load ? 'Fill Load' : $orderProduct->quantity;
                                $productsHtml .= "• {$quantity} x {$orderProduct->product->sku}<br>";
                            }

                            $stopLabel = $totalStops > 1 ? "Stop {$order->stop_number} - " : '';

                            $ordersHtml .= "
                                <div class='p-2 mb-3 rounded bg-gray-50'>
                                    <div class='font-medium'>{$stopLabel}{$order->customer->name}</div>
                                    <div class='text-sm text-gray-600'>{$order->location->full_address}</div>
                                    <div class='mt-1 text-sm text-gray-500'>{$productsHtml}</div>
                                </div>
                            ";
                        }

                        return "<div class='space-y-1'>{$ordersHtml}</div>";
                    })
                    ->alignLeft()
                    ->wrap(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'pending' => 'gray',
                        'in_progress' => 'warning',
                        // need a color for confirmed
                        'confirmed' => 'purple',
                        'completed' => 'success',
                        'cancelled' => 'danger',
                    }),
                Tables\Columns\TextColumn::make('scheduled_date')
                    ->date()
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\BulkAction::make('updateStatus')
                        ->label('Update Status')
                        ->icon('heroicon-o-truck')
                        ->action(function (Collection $records, array $data): void {
                            $records->each(function ($record) use ($data) {
                                $record->update(['status' => $data['status']]);
                            });
                        })
                        ->form([
                            Forms\Components\Select::make('status')
                                ->options([
                                    'pending' => 'Pending',
                                    'in_progress' => 'In Progress',
                                    'completed' => 'Completed',
                                    'cancelled' => 'Cancelled',
                                ])
                                ->required(),
                        ]),
                ]),
            ])
            ->defaultGroup('scheduled_date')
            ->groups([
                Tables\Grouping\Group::make('scheduled_date')
                    ->label('Delivery Date')
                    ->date()
                    ->collapsible()
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\OrdersRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTrips::route('/'),
            'create' => Pages\CreateTrip::route('/create'),
            'edit' => Pages\EditTrip::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with([
                'orders.customer',
                'orders.location',
                'orders.orderProducts.product',
                'driver'
            ]);
    }
}
