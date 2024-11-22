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
                Tables\Columns\TextColumn::make('order_number'),
                Tables\Columns\TextColumn::make('customer.name')
                    ->label('Customer'),
                Tables\Columns\TextColumn::make('location.city')
                    ->label('Delivery Location'),
                Tables\Columns\TextColumn::make('stop_number')
                    ->sortable(),
                Tables\Columns\TextColumn::make('delivery_notes'),
            ])
            ->defaultSort('stop_number')
            ->reorderable('stop_number')
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('remove')
                    ->action(fn (Order $record) => $record->update(['trip_id' => null]))
                    ->requiresConfirmation(),
            ])
            ->headerActions([
                Tables\Actions\Action::make('add_order')
                    ->form(function () {
                        // Calculate the next stop number
                        $nextStopNumber = $this->getOwnerRecord()
                            ->orders()
                            ->max('stop_number') ?? 0;

                        return [
                            Forms\Components\Select::make('order_id')
                                ->label('Order')
                                ->options(function () {
                                    return Order::query()
                                        ->whereNull('trip_id')
                                        ->whereNotIn('status', ['delivered', 'cancelled'])
                                        ->get()
                                        ->mapWithKeys(fn ($order) => [
                                            $order->id => "{$order->order_number} - {$order->customer->name} ({$order->location->city})"
                                        ]);
                                })
                                ->searchable()
                                ->required()
                                ->preload(),
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
                        $order = Order::find($data['order_id']);
                        $order->update([
                            'trip_id' => $trip->id,
                            'stop_number' => $data['stop_number'],
                            'delivery_notes' => $data['delivery_notes'] ?? null,
                        ]); 

                        Notification::make()
                            ->title('Order added to trip')
                            ->success()
                            ->send();
                    })
            ]);
    }
}