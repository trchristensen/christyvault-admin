<?php

namespace App\Filament\Resources\Traits;

use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\Product;
use Filament\Forms;
use Carbon\Carbon;
use Ysfkaya\FilamentPhoneInput\Forms\PhoneInput;
use Schmeits\FilamentCharacterCounter\Forms\Components\Textarea;
use Filament\Forms\Components\Split;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Grid;
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
                        ->options(function () {
                            return Customer::query()
                                ->with('locations')
                                ->get()
                                ->mapWithKeys(function ($customer) {
                                    return [$customer->id => view('filament.components.customer-option', [
                                        'name' => $customer->name,
                                        'location' => $customer->locations()->first()?->full_address,
                                        'email' => $customer->email,
                                        'phone' => $customer->phone,
                                    ])->render()];
                                });
                        })
                        ->required()
                        ->columnSpan([
                            'sm' => 12,
                            'md' => 8,
                        ])
                        ->searchable()
                        ->allowHtml()
                        ->reactive()
                        ->afterStateUpdated(function (callable $set, $state) {
                            if (!$state) {
                                $set('location_id', null);
                                return;
                            }

                            $location = Customer::find($state)?->locations()->first();
                            if ($location) {
                                $set('location_id', $location->id);
                            }
                        })
                        ->createOptionForm([
                            Forms\Components\TextInput::make('name')
                                ->required()
                                ->maxLength(255),
                            Forms\Components\TextInput::make('email')
                                ->email()
                                ->maxLength(255),
                            PhoneInput::make('phone')
                                ->defaultCountry('US'),
                            Forms\Components\TextInput::make('contact_name')
                                ->maxLength(255)
                                ->label('Contact Name'),

                            Forms\Components\Section::make('Location')
                                ->schema([
                                    Forms\Components\TextInput::make('location.address_line1')
                                        ->required()
                                        ->maxLength(255),
                                    Forms\Components\TextInput::make('location.address_line2')
                                        ->maxLength(255),
                                    Grid::make([
                                        'default' => 1,
                                        'sm' => 1,
                                        'md' => 12,
                                    ])
                                        ->schema([
                                            Forms\Components\TextInput::make('location.city')
                                                ->required()
                                                ->maxLength(255)
                                                ->columnSpan([
                                                    'default' => 1,
                                                    'sm' => 1,
                                                    'md' => 6,
                                                ]),
                                            Forms\Components\TextInput::make('location.state')
                                                ->required()
                                                ->default('CA')
                                                ->maxLength(255)
                                                ->columnSpan([
                                                    'default' => 1,
                                                    'sm' => 1,
                                                    'md' => 3,
                                                ]),
                                            Forms\Components\TextInput::make('location.postal_code')
                                                ->required()
                                                ->maxLength(20)
                                                ->columnSpan([
                                                    'default' => 1,
                                                    'sm' => 1,
                                                    'md' => 3,
                                                ]),
                                        ]),
                                    Forms\Components\Select::make('location.location_type')
                                        ->options([
                                            'business' => 'Business',
                                            'residential' => 'Residential',
                                            'funeral_home' => 'Funeral Home',
                                            'cemetery' => 'Cemetery',
                                            'other' => 'Other',
                                        ])
                                        ->required(),
                                ])
                        ])
                        ->createOptionUsing(function (array $data, $set) {
                            $customer = Customer::create([
                                'name' => $data['name'],
                                'email' => $data['email'],
                                'phone' => $data['phone'],
                            ]);

                            $location = $customer->locations()->create([
                                'name' => $data['name'],
                                'address_line1' => $data['location']['address_line1'],
                                'address_line2' => $data['location']['address_line2'],
                                'city' => $data['location']['city'],
                                'state' => $data['location']['state'],
                                'postal_code' => $data['location']['postal_code'],
                                'location_type' => $data['location']['location_type'],
                            ]);

                            $set('location_id', $location->id);

                            return $customer->id;
                        }),
                    Forms\Components\Hidden::make('location_id')
                        ->afterStateUpdated(function ($state, $set, $get) {
                            if ($customerId = $get('customer_id')) {
                                $customer = Customer::find($customerId);
                                if ($customer && $customer->locations()->first()) {
                                    $set('location_id', $customer->locations()->first()->id);
                                }
                            }
                        }),
                    Forms\Components\Select::make('status')
                        ->options(collect(OrderStatus::cases())->mapWithKeys(function ($status) {
                            return [$status->value => str($status->value)
                                ->replace('_', ' ')
                                ->title()
                                ->toString()];
                        }))
                        ->columnSpan([
                            'sm' => 4,
                            'md' => 4,
                        ])
                        ->default(OrderStatus::PENDING->value)
                        ->reactive()
                        ->required(),
                    Forms\Components\DatePicker::make('order_date')
                        ->required()
                        ->columnSpan([
                            'sm' => 4,
                            'md' => 4,
                        ])
                        ->native(false)
                        ->default(now()->toDateString()),
                    Forms\Components\DatePicker::make('requested_delivery_date')
                        ->required()
                        ->native(false)
                        ->columnSpan([
                            'sm' => 4,
                            'md' => 4,
                        ])
                        // ->default(now()),
                        ->default(fn() => $defaultDate ?? now()),  // Use passed date or fallback to now()
                    Forms\Components\DatePicker::make('assigned_delivery_date')
                        ->native(false)
                        ->columnSpan([
                            'sm' => 4,
                            'md' => 4,
                        ]),
                    // ->default(fn() => $defaultDate ?? now()),  // Use passed date or fallback to now()

                    // ->minDate(today()),

                    Forms\Components\TimePicker::make('delivery_time')
                        ->label("Deliver By time")
                        ->nullable()
                        ->columnSpan([
                            'sm' => 4,
                            'md' => 4,
                        ])
                        ->seconds(false),
                    Forms\Components\DateTimePicker::make('service_date')
                        ->nullable()
                        ->native(false)
                        ->seconds(false)
                        ->columnSpan([
                            'sm' => 4,
                            'md' => 4,
                        ]),
                    Forms\Components\Select::make('plant_location')
                        ->label('Delivery Type')
                        ->options(function () {
                            return collect(\App\Enums\PlantLocation::cases())->mapWithKeys(fn($location) => [
                                $location->value => $location->getLabel(),
                            ]);
                        })
                        ->default(\App\Enums\PlantLocation::COLMA_MAIN->value)
                        ->columnSpan([
                            'sm' => 4,
                            'md' => 4,
                        ]),
                    Textarea::make('special_instructions')
                        ->columnSpan(12)
                        ->characterLimit(166),
                ])
                ->columns(12),
            Forms\Components\Section::make('Products')
                ->compact()
                ->schema([
                    Forms\Components\Repeater::make('orderProducts')
                        ->label(false)
                        ->addActionLabel('Add a Product')
                        ->relationship()
                        ->schema([
                            Forms\Components\Select::make('product_id')
                                ->createOptionForm([
                                    Forms\Components\TextInput::make('sku')
                                        ->required()
                                        ->label("Product Number")
                                        ->maxLength(255),
                                    Forms\Components\TextInput::make('name')
                                        ->label("Product Name")
                                        ->required()
                                        ->maxLength(255),
                                    Forms\Components\TextInput::make('description')
                                        ->maxLength(255),
                                    Forms\Components\TextInput::make('price')  // Add price field
                                        ->required()
                                        ->numeric()
                                        ->default(0)
                                        ->prefix('$')
                                        ->label('Price'),
                                    // stock
                                    Forms\Components\TextInput::make('stock')
                                        ->required()
                                        ->numeric()
                                        ->default(0)
                                        ->label('Stock'),
                                ])
                                ->createOptionUsing(function (array $data): int {
                                    return Product::create($data)->getKey();
                                })
                                ->createOptionAction(function (Forms\Components\Actions\Action $action) {
                                    return $action
                                        ->modalHeading('Create new product')
                                        ->modalWidth('lg');
                                })
                                ->columnSpan([
                                    'default' => 12,
                                    'sm' => 12,
                                    'md' => 12,
                                    'lg' => 5,
                                ])
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
                                ->label('Fill load')
                                ->columnSpan([
                                    'default' => 12,
                                    'sm' => 4,
                                    'md' => 4,
                                    'lg' => 1,
                                ])
                                ->inline(false)
                                ->reactive()
                                ->afterStateUpdated(function ($state, callable $set) {
                                    if ($state) {
                                        $set('quantity', null);
                                    }
                                }),
                            Forms\Components\TextInput::make('quantity')
                                ->numeric()
                                ->columnSpan([
                                    'default' => 12,
                                    'sm' => 4,
                                    'md' => 4,
                                    'lg' => 1,
                                ])
                                ->default(1)
                                ->required(fn(Forms\Get $get): bool => !$get('fill_load'))
                                ->disabled(fn(Forms\Get $get): bool => $get('fill_load'))
                                ->dehydrated(fn(Forms\Get $get): bool => !$get('fill_load')),
                            Forms\Components\TextInput::make('quantity_delivered')
                                ->label('Delivered')
                                ->columnSpan([
                                    'default' => 12,
                                    'sm' => 4,
                                    'md' => 4,
                                    'lg' => 1,
                                ])
                                ->numeric()
                                // disabled if status is not delivered. the line below is not working
                                ->disabled(function (\Filament\Forms\Get $get): bool {
                                    $status = $get('../../status');
                                    return !in_array($status, [
                                        OrderStatus::DELIVERED->value,
                                        OrderStatus::INVOICED->value,
                                        OrderStatus::COMPLETED->value,
                                    ]);
                                }),
                            Forms\Components\Hidden::make('price')
                                ->default(0),
                            Forms\Components\TextInput::make('location')
                                ->live()
                                ->nullable()
                                ->columnSpan([
                                    'default' => 12,
                                    'sm' => 12,
                                    'md' => 12,
                                    'lg' => 4,
                                ]),
                            Forms\Components\TextInput::make('notes')
                                ->live()
                                ->nullable()
                                ->columnSpan(12)
                        ])
                        ->columns(12)
                ]),
        ];
    }
}
