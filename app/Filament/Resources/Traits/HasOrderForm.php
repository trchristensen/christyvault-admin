<?php

namespace App\Filament\Resources\Traits;

use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\Product;
use Filament\Forms;
use Carbon\Carbon;

trait HasOrderForm
{
    public static function getOrderFormSchema(?string $defaultDate = null): array
    {
        return [
            Forms\Components\Section::make('Order Details')
                ->description(fn($record) => $record?->order_number)
                ->schema([
                    Forms\Components\Select::make('customer_id')
                        ->label('Customer')
                        ->options(Customer::pluck('name', 'id'))
                        ->required()
                        ->searchable()
                        ->reactive()
                        ->columnSpanFull()
                        ->createOptionForm([
                            Forms\Components\TextInput::make('name')
                                ->required()
                                ->maxLength(255),
                            Forms\Components\TextInput::make('email')
                                ->email()

                                ->maxLength(255),
                            Forms\Components\TextInput::make('phone')
                                ->tel()

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

                            $locations = Customer::find($state)?->locations()->get();

                            if ($locations && $locations->count() === 1) {
                                $set('location_id', $locations->first()->id);
                            } else {
                                $set('location_id', null);
                            }
                        }),
                    Forms\Components\Select::make('location_id')
                        ->label('Delivery Location')
                        ->columnSpanFull()
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
                            Forms\Components\TextInput::make('address_line1')
                                ->required()
                                ->maxLength(255),
                            Forms\Components\TextInput::make('address_line2')
                                ->maxLength(255),
                            Forms\Components\TextInput::make('city')
                                ->required()
                                ->maxLength(255),
                            Forms\Components\TextInput::make('state')
                                ->required()
                                ->maxLength(255),
                            Forms\Components\TextInput::make('postal_code')
                                ->required()
                                ->maxLength(20),
                            Forms\Components\Select::make('location_type')
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
                        ->default(now()->toDateString()),
                    Forms\Components\DatePicker::make('requested_delivery_date')
                        ->required()
                        ->native(false)
                        // ->default(now()),
                        ->default(fn() => $defaultDate ?? now()),  // Use passed date or fallback to now()
                    Forms\Components\DatePicker::make('assigned_delivery_date')
                        ->native(false),
                        // ->default(fn() => $defaultDate ?? now()),  // Use passed date or fallback to now()

                    // ->minDate(today()),
                    Forms\Components\Select::make('status')
                        ->options(collect(OrderStatus::cases())->mapWithKeys(function ($status) {
                            return [$status->value => str($status->value)
                                ->replace('_', ' ')
                                ->title()
                                ->toString()];
                        }))
                        ->default(OrderStatus::PENDING->value)
                        ->reactive()
                        ->required(),
                    Forms\Components\TimePicker::make('delivery_time')
                        ->label("Deliver By")
                        ->nullable()
                        ->seconds(false),
                    Forms\Components\DateTimePicker::make('service_date')
                        ->nullable()
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
                                ->columnSpan(6)
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
                                ->allowHtml()
                                ->required()
                                ->reactive()
                                ->searchable()
                                ->afterStateUpdated(
                                    fn($state, callable $set) =>
                                    $set('price', Product::find($state)?->price ?? 0)
                                ),
                            Forms\Components\Toggle::make('fill_load')
                                ->label('Fill out load')
                                ->inline(false)
                                ->reactive()
                                ->afterStateUpdated(function ($state, callable $set) {
                                    if ($state) {
                                        $set('quantity', null);
                                    }
                                }),
                            Forms\Components\TextInput::make('quantity')
                                ->numeric()
                                ->default(1)
                                ->required(fn(Forms\Get $get): bool => !$get('fill_load'))
                                ->disabled(fn(Forms\Get $get): bool => $get('fill_load'))
                                ->dehydrated(fn(Forms\Get $get): bool => !$get('fill_load')),
                            Forms\Components\TextInput::make('quantity_delivered')
                                ->label('Delivered')
                                ->numeric()
                                // disabled if status is not delivered. the line below is not working
                                ->disabled(function (\Filament\Forms\Get $get): bool {
                                    $status = $get('../../status');
                                    logger()->info('status', ['status' => $status]);
                                    return !in_array($status, [
                                        OrderStatus::DELIVERED->value,
                                        OrderStatus::INVOICED->value,
                                        OrderStatus::COMPLETED->value,
                                    ]);
                                }),
                            Forms\Components\Hidden::make('price')
                                ->default(0),
                            Forms\Components\TextInput::make('location')
                                ->nullable()
                                ->columnSpan(6),
                            Forms\Components\TextInput::make('notes')
                                ->nullable()
                                ->columnSpan(6)
                        ])
                        ->columns(3)
                ]),
        ];
    }
}
