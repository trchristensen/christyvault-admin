<?php

namespace App\Filament\Resources\TripResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use App\Models\Order;
use App\Models\Trip;
use Filament\Notifications\Notification;

class OrdersRelationManager extends RelationManager
{
    protected static string $relationship = 'orders';
    protected static ?string $recordTitleAttribute = 'order_number';
    protected static ?string $title = 'Trip Orders';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('order_number')
                    ->label('Order')
                    ->disabled()
                    ->dehydrated(false),
                Forms\Components\TextInput::make('stop_number')
                    ->numeric()
                    ->required(),
                Forms\Components\TextInput::make('delivery_notes')
                    ->nullable(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('order_details')
                    ->label('Order Details')
                    ->html()
                    ->alignLeft()
                    ->wrap()
                    ->state(function ($record): string {
                        // Build products HTML
                        $productsHtml = collect($record->orderProducts)->map(function ($orderProduct) {
                            $quantity = $orderProduct->fill_load ? 'Fill Load' : $orderProduct->quantity;
                            return "• {$quantity} x {$orderProduct->product->name}";
                        })->join('<br>');

                        // Format dates
                        $requestedDate = $record->requested_delivery_date?->format('M j, Y') ?? 'Not set';
                        $assignedDate = $record->assigned_delivery_date?->format('M j, Y') ?? 'Not set';

                        return "
                            <div class='p-2 rounded bg-gray-50'>
                                <div class='grid grid-cols-2 gap-4'>
                                    <div>
                                        <div class='font-medium text-primary-600'>{$record->order_number}</div>
                                        <div class='mt-1 text-sm font-medium'>{$record->location->name}</div>
                                        <div class='text-sm text-gray-600'>{$record->location->full_address}</div>
                                    </div>
                                    <div>
                                        <div class='text-sm'>
                                            <span class='font-medium'>Stop:</span> {$record->stop_number}
                                        </div>
                                        <div class='text-sm'>
                                            <span class='font-medium'>Requested:</span> {$requestedDate}
                                        </div>
                                        <div class='text-sm'>
                                            <span class='font-medium'>Assigned:</span> {$assignedDate}
                                        </div>
                                    </div>
                                </div>
                                <div class='pt-2 mt-2 text-sm text-gray-500 border-t'>
                                    <div class='mb-1 font-medium'>Products:</div>
                                    {$productsHtml}
                                </div>
                                " . ($record->delivery_notes ? "
                                <div class='pt-2 mt-2 text-sm text-gray-600 border-t'>
                                    <span class='font-medium'>Delivery Notes:</span> {$record->delivery_notes}
                                </div>" : "") . "
                            </div>
                        ";
                    }),
            ])
            ->defaultSort('stop_number')
            ->reorderable('stop_number')
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('remove')
                    ->action(fn(Order $record) => $record->update(['trip_id' => null]))
                    ->requiresConfirmation(),
            ])
            ->headerActions([
                Tables\Actions\Action::make('add_order')
                    ->form(function () {
                        $nextStopNumber = $this->getOwnerRecord()
                            ->orders()
                            ->max('stop_number') ?? 0;

                        return [
                            Forms\Components\Select::make('order_id')
                                ->label('Order')
                                ->options(
                                    Order::query()
                                        ->whereNull('trip_id')
                                        ->whereNotIn('status', ['delivered', 'cancelled'])
                                        ->with(['location', 'orderProducts.product'])
                                        ->get()
                                        ->mapWithKeys(function ($order) {
                                            // Build products HTML
                                            $productsHtml = collect($order->orderProducts)->map(function ($orderProduct) {
                                                $quantity = $orderProduct->fill_load ? 'Fill Load' : $orderProduct->quantity;
                                                return "• {$quantity} x {$orderProduct->product->name}";
                                            })->join('<br>');

                                            // Format dates
                                            $requestedDate = $order->requested_delivery_date?->format('M j, Y') ?? 'Not set';
                                            $assignedDate = $order->assigned_delivery_date?->format('M j, Y') ?? 'Not set';

                                            // Build the option HTML
                                            $html = "
                                                <div class='p-2'>
                                                    <div class='font-medium text-primary-600'>{$order->order_number} - {$order->location->name}</div>
                                                    <div class='mt-1 text-sm text-gray-600'>{if($order->location) {$order->location->full_address} {else} {$order->location->full_address} {/if}</div>
                                                    <div class='grid grid-cols-2 gap-2 mt-1 text-sm'>
                                                        <div>
                                                            <span class='font-medium'>Requested:</span> {$requestedDate}
                                                        </div>
                                                        <div>
                                                            <span class='font-medium'>Assigned:</span> {$assignedDate}
                                                        </div>
                                                    </div>
                                                    <div class='mt-1 text-sm text-gray-500'>{$productsHtml}</div>
                                                </div>
                                            ";

                                            return [$order->id => $html];
                                        })
                                        ->toArray()
                                )
                                ->searchable()
                                ->required()
                                ->preload()
                                ->allowHtml(),
                            Forms\Components\TextInput::make('stop_number')
                                ->numeric()
                                ->required()
                                ->default($nextStopNumber + 1),
                            Forms\Components\TextInput::make('delivery_notes')
                                ->nullable(),
                        ];
                    })
                    ->action(function (array $data): void {
                        $trip = $this->getOwnerRecord();
                        $order = Order::findOrFail($data['order_id']);

                        try {
                            $order->update([
                                'trip_id' => $trip->id,
                                'stop_number' => $data['stop_number'],
                                'delivery_notes' => $data['delivery_notes'] ?? null,
                                'assigned_delivery_date' => $trip->scheduled_date,
                                // 'status' => 'assigned'
                            ]);

                            Notification::make()
                                ->title('Order added to trip')
                                ->success()
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Error adding order')
                                ->danger()
                                ->body($e->getMessage())
                                ->send();
                        }
                    })
            ]);
    }

    public function getTableColumns(): array
    {
        return [
            Tables\Columns\TextColumn::make('order_number')
                ->searchable(),
            Tables\Columns\TextColumn::make('location.name')
                ->searchable(),
            // ... other columns ...
        ];
    }
}
