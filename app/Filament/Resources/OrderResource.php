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

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationGroup = 'Delivery Management';


    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Order Details')
                    ->description(fn(Order $order): mixed => $order->order_number)
                    ->schema([
                        Forms\Components\Select::make('customer_id')
                            ->label('Customer')
                            ->options(Customer::pluck('name', 'id'))
                            ->required()
                            ->searchable()
                            ->reactive()
                            ->createOptionForm([
                                Forms\Components\TextInput::make('name')
                                    ->required()
                                    ->maxLength(255),
                                Forms\Components\TextInput::make('email')
                                    ->email()
                                    ->required()
                                    ->maxLength(255),
                                Forms\Components\TextInput::make('phone')
                                    ->tel()
                                    ->required()
                                    ->maxLength(255),
                            ])
                            ->createOptionUsing(function (array $data) {
                                return Customer::create([
                                    'name' => $data['name'],
                                    'email' => $data['email'],
                                    'phone' => $data['phone'],
                                ])->id;
                            })
                            ->afterStateUpdated(function (callable $set, $state) {
                                if (!$state) {
                                    $set('location_id', null);
                                    return;
                                }

                                // Get the customer's locations
                                $locations = Customer::find($state)?->locations()->get();

                                // If there's exactly one location, set it automatically
                                if ($locations && $locations->count() === 1) {
                                    $set('location_id', $locations->first()->id);
                                } else {
                                    $set('location_id', null);
                                }
                            }),
                        Forms\Components\Select::make('location_id')
                            ->label('Delivery Location')
                            ->options(function (callable $get) {
                                $customerId = $get('customer_id');
                                if (!$customerId) return [];

                                return Customer::find($customerId)
                                    ?->locations()
                                    ->get()
                                    ->mapWithKeys(fn($location) => [
                                        $location->id => $location->full_address
                                    ]) ?? [];
                            })
                            ->required()
                            ->searchable()
                            ->createOptionForm([
                                Forms\Components\TextInput::make('name')
                                    ->required()
                                    ->maxLength(255),
                                Forms\Components\TextInput::make('address_line1')  // Changed from 'address'
                                    ->required()
                                    ->maxLength(255),
                                Forms\Components\TextInput::make('address_line2')  // Added this optional field
                                    ->maxLength(255),
                                Forms\Components\TextInput::make('city')
                                    ->required()
                                    ->maxLength(255),
                                Forms\Components\TextInput::make('state')
                                    ->required()
                                    ->maxLength(255),
                                Forms\Components\TextInput::make('postal_code')    // Changed from 'zip'
                                    ->required()
                                    ->maxLength(20),
                                Forms\Components\Select::make('location_type')     // Added required field
                                    ->options([
                                        'business' => 'Business',
                                        'residential' => 'Residential',
                                        'funeral_home' => 'Funeral Home',
                                        'cemetery' => 'Cemetery',
                                        'other' => 'Other',
                                    ])
                                    ->required(),
                            ])
                            ->createOptionUsing(function (array $data, callable $get) {
                                $customerId = $get('customer_id');

                                return Customer::find($customerId)->locations()->create([
                                    'name' => $data['name'],
                                    'address_line1' => $data['address_line1'],
                                    'address_line2' => $data['address_line2'],
                                    'city' => $data['city'],
                                    'state' => $data['state'],
                                    'postal_code' => $data['postal_code'],
                                    'location_type' => $data['location_type'],
                                ])->id;
                            })
                            ->disabled(fn(callable $get) => empty($get('customer_id'))),
                        Forms\Components\DatePicker::make('order_date')
                            ->required()
                            ->native(false)
                            ->prefixIcon('heroicon-o-calendar')
                            ->default(now()->toDateString()),
                        Forms\Components\DatePicker::make('requested_delivery_date')
                            ->native(false)
                            ->required()
                            ->prefixIcon('heroicon-o-calendar')
                            ->default(now()),
                        Forms\Components\DatePicker::make('assigned_delivery_date')
                            ->native(false)
                            ->disabledDates(
                                function () {
                                    $dates = [];
                                    $startDate = today();
                                    $endDate = today()->addYear();

                                    foreach (CarbonPeriod::create($startDate, $endDate) as $date) {
                                        if ($date->isWeekend()) {
                                            $dates[] = $date->format('Y-m-d');
                                        }
                                    }

                                    return  $dates;
                                }
                            )
                            ->minDate(today()),
                        Forms\Components\Select::make('status')
                            ->options([
                                'pending' => 'Pending',
                                'confirmed' => 'Confirmed',
                                'in_production' => 'In Production',
                                'ready_for_delivery' => 'Ready for Delivery',
                                'out_for_delivery' => 'Out for Delivery',
                                'delivered' => 'Delivered',
                                'cancelled' => 'Cancelled',
                            ])
                            ->default('pending')
                            ->required(),
                        Forms\Components\TimePicker::make('delivery_time')
                            ->label("Deliver By")
                            ->nullable()
                            ->seconds(false),
                        Forms\Components\DateTimePicker::make('service_date')
                            ->nullable()
                            ->prefixIcon('heroicon-o-calendar')
                            ->native(false)
                            ->seconds(false),
                        Forms\Components\Textarea::make('special_instructions')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
                Forms\Components\Section::make('Products')
                    ->schema([
                        Forms\Components\Repeater::make('orderProducts')
                            ->relationship()

                            ->schema([
                                Forms\Components\Select::make('product_id')
                                    ->columnSpanFull()
                                    ->label('Product')
                                    ->options(
                                        Product::query()
                                            ->active()
                                            ->get()
                                            ->mapWithKeys(fn(Product $product) => [
                                                $product->id => view('filament.components.product-option', [
                                                    'sku' => $product->sku,
                                                    'name' => $product->name,
                                                ])->render()
                                            ])
                                    )
                                    ->getOptionLabelsUsing(
                                        fn(array $values): array =>
                                        Product::whereIn(
                                            'id',
                                            $values
                                        )
                                            ->get()
                                            ->mapWithKeys(fn(Product $product) => [
                                                $product->id => view('filament.components.product-option', [
                                                    'sku' => $product->sku,
                                                    'name' => $product->name,
                                                ])->render()
                                            ])

                                            ->toArray()
                                    )
                                    ->allowHtml()
                                    ->required()
                                    ->reactive()
                                    ->searchable()
                                    ->afterStateUpdated(
                                        fn($state, callable $set) =>
                                        $set('price', Product::find($state)?->price ?? 0)
                                    ),
                                Toggle::make('fill_load')
                                    ->label('Fill out load')

                                    ->inline(false)
                                    ->reactive()
                                    ->dehydrateStateUsing(fn($state) => (bool) $state)
                                    ->afterStateUpdated(function ($state, callable $set) {
                                        if ($state) {
                                            $set('quantity', null);
                                        }
                                    }),

                                // Forms\Components\Checkbox::make('fill_load')
                                //     ->label('Fill out load')
                                //     ->reactive()
                                //     ->afterStateUpdated(function ($state, callable $set) {
                                //         if ($state) {
                                //             $set('quantity', null);
                                //         }
                                //     }),
                                Forms\Components\TextInput::make('quantity')
                                    ->numeric()
                                    ->default(1)
                                    ->required(fn(Forms\Get $get): bool => !$get('fill_load'))
                                    ->disabled(fn(Forms\Get $get): bool => $get('fill_load'))
                                    ->dehydrated(fn(Forms\Get $get): bool => !$get('fill_load')),
                                Forms\Components\Hidden::make('price')
                                    ->default(0),
                                Forms\Components\TextInput::make('location')
                                    ->nullable(),
                                Forms\Components\TextInput::make('notes')
                                    ->nullable()
                                    ->columnSpanFull()
                            ])
                            ->columns(3)
                    ]),
            ]);
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
