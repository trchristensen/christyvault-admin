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

class TripResource extends Resource
{
    protected static ?string $model = Trip::class;

    protected static ?string $navigationIcon = 'heroicon-o-truck';
    protected static ?string $navigationGroup = 'Delivery Management';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Trip Information')
                    ->schema([
                        Forms\Components\TextInput::make('trip_number')
                            ->required()
                            ->default(function () {
                                $latestTrip = Trip::withTrashed()
                                    ->orderBy('id', 'desc')
                                    ->first();

                                $lastNumber = 0;
                                if ($latestTrip && $latestTrip->trip_number) {
                                    preg_match('/TRIP-(\d+)/', $latestTrip->trip_number, $matches);
                                    $lastNumber = isset($matches[1]) ? (int)$matches[1] : 0;
                                }

                                $nextNumber = $lastNumber + 1;
                                return sprintf('TRIP-%05d', $nextNumber);
                            })
                            ->readOnly(),

                        Forms\Components\Select::make('driver_id')
                            ->relationship('driver', 'name')
                            ->placeholder('Select Driver')
                            ->required()
                            ->options(Employee::where('position', 'driver')->pluck('name', 'id'))
                            ->preload(),
                        Forms\Components\Select::make('status')
                            ->options([
                                'pending' => 'Pending',
                                'confirmed' => 'Confirmed',
                                'in_progress' => 'In Progress',
                                'completed' => 'Completed',
                                'cancelled' => 'Cancelled',
                            ])
                            ->default('pending')
                            ->required(),
                        Forms\Components\DatePicker::make('scheduled_date')
                            ->required()
                            ->afterStateUpdated(function ($state, $record) {
                                if ($record && $record->scheduled_date) {
                                    Notification::make()
                                        ->warning()
                                        ->title('Note')
                                        ->body('Changing the trip date will automatically update all associated order delivery dates.')
                                        ->send();
                                }
                            }),
                        Forms\Components\TimePicker::make('start_time'),
                        Forms\Components\TimePicker::make('end_time'),
                        Forms\Components\Textarea::make('notes')
                            ->columnSpanFull(),
                    ])->columns(2),
            ]);
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
                                $productsHtml .= "• {$quantity} x {$orderProduct->product->name}<br>";
                            }

                            $stopLabel = $totalStops > 1 ? "Stop {$order->stop_number} - " : '';

                            $ordersHtml .= "
                                <div class='mb-3 p-2 bg-gray-50 rounded'>
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
