<?php

namespace App\Filament\Resources\Traits;

use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Forms\Components\DatePicker;
use App\Models\Employee;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Forms\Components\Hidden;
use App\Enums\OrderStatus;
use App\Enums\PlantLocation;
use App\Models\Location;
use App\Models\Product;
use Filament\Forms;
use Carbon\Carbon;
use Ysfkaya\FilamentPhoneInput\Forms\PhoneInput;
use Schmeits\FilamentCharacterCounter\Forms\Components\Textarea;
use Filament\Forms\Components\Split;

trait HasOrderForm
{
    public static function getOrderFormSchema(?string $defaultDate = null): array
    {
        return [
            Section::make('Order Details')
                ->columnSpanFull()
                ->description(fn($record) => $record?->order_number)
                ->schema([
                    Select::make('location_id')
                        ->label('Location')
                        ->options(function () {
                            return Location::query()
                                ->with('preferredDeliveryContact')
                                ->get()
                                ->mapWithKeys(function ($location) {
                                    return [$location->id => view('filament.components.location-option', [
                                        'name' => $location->name,
                                        'address' => $location->full_address,
                                        'contact' => $location->preferredDeliveryContact?->name,
                                        'phone' => $location->preferredDeliveryContact?->phone,
                                    ])->render()];
                                });
                        })
                        ->required()
                        ->columnSpan([
                            'default' => 12,
                            'sm' => 12,
                            'md' => 8,
                        ])
                        ->columnSpanFull()
                        ->searchable()
                        ->allowHtml()
                        ->reactive()
                        ->afterStateUpdated(function ($state, Set $set) {
                            if (!$state) return;

                            $location = Location::find($state);
                            if ($location) {
                                // Prefer the location's saved default, with the previous city-based rule as a fallback.
                                if ($location->default_plant_location) {
                                    $set('plant_location', $location->default_plant_location->value);
                                } elseif (strtolower($location->city) === 'colma' || strtolower($location->city) === 'south san francisco') {
                                    $set('plant_location', PlantLocation::COLMA_LOCALS->value);
                                } else {
                                    $set('plant_location', PlantLocation::COLMA_MAIN->value);
                                }

                                // Set ordered_by if preferred delivery contact exists
                                if ($location->preferredDeliveryContact?->name) {
                                    $set('ordered_by', $location->preferredDeliveryContact->name);
                                }
                            }
                        })
                        ->suffixAction(function ($state) {
                            if (!$state) return null;

                            return Action::make('edit')
                                ->icon('heroicon-m-pencil-square')
                                ->modalHeading('Edit Location')
                                ->modalSubmitActionLabel('Save changes')
                                ->schema([
                                    TextInput::make('name')
                                        ->required()
                                        ->maxLength(255),
                                    TextInput::make('address_line1')
                                        ->required()
                                        ->maxLength(255),
                                    TextInput::make('address_line2')
                                        ->maxLength(255),
                                    TextInput::make('city')
                                        ->required()
                                        ->maxLength(255),
                                    TextInput::make('state')
                                        ->required()
                                        ->default('CA')
                                        ->maxLength(255),
                                    TextInput::make('postal_code')
                                        ->required()
                                        ->maxLength(20),
                                    PhoneInput::make('phone')
                                        ->defaultCountry('US'),
                                    Select::make('location_type')
                                        ->options([
                                            'business' => 'Business',
                                            'residential' => 'Residential',
                                            'funeral_home' => 'Funeral Home',
                                            'cemetery' => 'Cemetery',
                                            'other' => 'Other',
                                        ])
                                        ->required(),
                                    Select::make('default_plant_location')
                                        ->label('Default Delivery Type')
                                        ->options(collect(PlantLocation::cases())->mapWithKeys(fn(PlantLocation $location) => [
                                            $location->value => $location->getLabel(),
                                        ]))
                                        ->default(PlantLocation::COLMA_MAIN->value)
                                        ->required(),
                                ])
                                ->fillForm(fn() => Location::find($state)->toArray())
                                ->action(function (array $data, $state): void {
                                    $location = Location::find($state);
                                    $location->update($data);
                                })
                                ->modalWidth('lg')
                                ->visible(fn($state): bool => (bool)$state);
                        })
                        ->createOptionForm([
                            TextInput::make('name')
                                ->required()
                                ->maxLength(255),
                            TextInput::make('address_line1')
                                ->required()
                                ->maxLength(255),
                            TextInput::make('address_line2')
                                ->maxLength(255),
                            Grid::make([
                                'default' => 1,
                                'sm' => 1,
                                'md' => 12,
                            ])
                                ->schema([
                                    TextInput::make('city')
                                        ->required()
                                        ->maxLength(255)
                                        ->columnSpan([
                                            'default' => 1,
                                            'sm' => 1,
                                            'md' => 6,
                                        ]),
                                    TextInput::make('state')
                                        ->required()
                                        ->default('CA')
                                        ->maxLength(255)
                                        ->columnSpan([
                                            'default' => 1,
                                            'sm' => 1,
                                            'md' => 3,
                                        ]),
                                    TextInput::make('postal_code')
                                        ->required()
                                        ->maxLength(20)
                                        ->columnSpan([
                                            'default' => 1,
                                            'sm' => 1,
                                            'md' => 3,
                                        ]),
                                ]),
                            Select::make('location_type')
                                ->options([
                                    'business' => 'Business',
                                    'residential' => 'Residential',
                                    'funeral_home' => 'Funeral Home',
                                    'cemetery' => 'Cemetery',
                                    'other' => 'Other',
                                ])
                                ->required(),
                            Select::make('default_plant_location')
                                ->label('Default Delivery Type')
                                ->options(collect(PlantLocation::cases())->mapWithKeys(fn(PlantLocation $location) => [
                                    $location->value => $location->getLabel(),
                                ]))
                                ->default(PlantLocation::COLMA_MAIN->value)
                                ->required(),
                            PhoneInput::make('phone')
                                ->defaultCountry('US'),

                            Section::make('Contact')
                                ->schema([
                                    TextInput::make('contact.name')
                                        ->required()
                                        ->label('Contact Name'),
                                    PhoneInput::make('contact.phone')
                                        ->defaultCountry('US'),
                                    PhoneInput::make('contact.mobile_phone')
                                        ->label('Mobile Phone')
                                        ->defaultCountry('US'),
                                ])
                        ])
                        ->createOptionUsing(function (array $data): int {
                            $location = Location::create([
                                'name' => $data['name'],
                                'address_line1' => $data['address_line1'],
                                'address_line2' => $data['address_line2'],
                                'city' => $data['city'],
                                'state' => $data['state'],
                                'postal_code' => $data['postal_code'],
                                'phone' => $data['phone'],
                                'location_type' => $data['location_type'],
                                'default_plant_location' => $data['default_plant_location'] ?? PlantLocation::COLMA_MAIN->value,
                            ]);

                            if (isset($data['contact'])) {
                                $contact = $location->contacts()->create([
                                    'name' => $data['contact']['name'],
                                    'phone' => $data['contact']['phone'],
                                    'mobile_phone' => $data['contact']['mobile_phone'],
                                ]);

                                $location->update(['preferred_delivery_contact_id' => $contact->id]);
                            }

                            return $location->id;
                        }),

                    TextInput::make('customer_order_number')
                        ->label('Customer Order #')
                        ->nullable()
                        ->maxLength(255)
                        ->columnSpan([
                            'sm' => 12,
                            'md' => 4,
                        ]),

                    Select::make('status')
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
                    DatePicker::make('order_date')
                        ->required()
                        ->columnSpan([
                            'sm' => 4,
                            'md' => 4,
                        ])
                        ->native(false)
                        ->default(now()->toDateString()),
                    DatePicker::make('requested_delivery_date')
                        ->required()
                        ->native(false)
                        ->columnSpan([
                            'sm' => 4,
                            'md' => 4,
                        ])
                        ->default(fn() => $defaultDate ?? now()),
                    DatePicker::make('assigned_delivery_date')
                        ->native(false)
                        ->columnSpan([
                            'sm' => 4,
                            'md' => 4,
                        ])
                        ->default(fn() => $defaultDate),
                    Select::make('driver_id')
                        ->label('Driver')
                        ->options(function () {
                            return Employee::whereHas('positions', function ($q) {
                                $q->where('name', 'driver');
                            })->pluck('name', 'id');
                        })
                        ->nullable()
                        ->searchable()
                        ->columnSpan([
                            'sm' => 4,
                            'md' => 4,
                        ])
                        ->placeholder('Select a driver'),
                    TimePicker::make('delivery_time')
                        ->label("Deliver By time")
                        ->nullable()
                        ->columnSpan([
                            'sm' => 4,
                            'md' => 4,
                        ])
                        ->seconds(false),
                    DateTimePicker::make('service_date')
                        ->nullable()
                        ->native(false)
                        ->seconds(false)
                        ->columnSpan([
                            'sm' => 4,
                            'md' => 4,
                        ]),
                    Select::make('plant_location')
                        ->label('Delivery Type')
                        ->options(function () {
                            return collect(PlantLocation::cases())->mapWithKeys(fn($location) => [
                                $location->value => $location->getLabel(),
                            ]);
                        })
                        ->default(PlantLocation::COLMA_MAIN->value)
                        ->columnSpan([
                            'sm' => 4,
                            'md' => 4,
                        ]),
                    TextInput::make('ordered_by')
                        ->label('Ordered By')
                        ->columnSpan([
                            'sm' => 4,
                            'md' => 4,
                        ]),
                    Textarea::make('special_instructions')
                        ->columnSpan(12)
                        ->label('Notes')
                        ->characterLimit(166),
                    FileUpload::make('delivery_tag_url')
                        ->label('Delivery Tag Attachment')
                        ->disk('r2')
                        ->directory('delivery-tags')
                        ->visibility('private')
                        ->downloadable()
                        ->openable()
                        ->acceptedFileTypes([
                            'application/pdf',
                            'image/png',
                            'image/jpeg',
                            'image/jpg',
                            'image/gif',
                            'image/webp',
                        ])
                        ->maxSize(5120) // 5MB limit
                        ->columnSpan([
                            'sm' => 12,
                            'md' => 6,
                        ])
                        ->helperText('Upload delivery tag document or image (PDF, JPG, PNG, etc.)')
                        ->getUploadedFileNameForStorageUsing(function ($file, $get, $record) {
                            $date = now()->format('Y-m-d');
                            $locationId = $get('location_id');
                            $extension = $file->getClientOriginalExtension();

                            if ($locationId) {
                                $location = Location::find($locationId);
                                if ($location) {
                                    // Clean the location name and city for filename
                                    $locationName = preg_replace('/[^A-Za-z0-9\-_]/', '_', $location->name);
                                    $city = preg_replace('/[^A-Za-z0-9\-_]/', '_', $location->city);

                                    // Include order number to prevent collisions (if record exists)
                                    if ($record && $record->order_number) {
                                        return "{$date}_{$locationName}_{$city}_{$record->order_number}_delivery_tag.{$extension}";
                                    } else {
                                        // For new orders, add timestamp to prevent collisions
                                        $timestamp = now()->format('His'); // HHMMSS
                                        return "{$date}_{$locationName}_{$city}_{$timestamp}_delivery_tag.{$extension}";
                                    }
                                }
                            }

                            // Fallback if no location is selected
                            if ($record && $record->order_number) {
                                return "{$date}_{$record->order_number}_delivery_tag.{$extension}";
                            } else {
                                // For new orders without location, use timestamp
                                $timestamp = now()->format('His');
                                return "{$date}_{$timestamp}_delivery_tag.{$extension}";
                            }
                        }),
                ])
                ->columns(12),
            Section::make('Products')
                ->columnSpanFull()
                ->compact()
                ->schema([
                    Repeater::make('orderProducts')
                        ->columnSpanFull()
                        ->label(false)
                        ->addActionLabel('Add a Product')
                        ->schema([
                            Grid::make(12)
                                ->columnSpanFull()
                                ->schema([
                                    Toggle::make('is_custom_product')
                                        ->label('Custom Product')
                                        ->columnSpan(1)
                                        ->inline(false)
                                        ->reactive()
                                        ->afterStateUpdated(function ($state, callable $set) {
                                            if ($state) {
                                                $set('product_id', null);
                                            } else {
                                                $set('custom_description', null);
                                            }
                                        })
                                        ->live('blur'),
                                    Select::make('product_id')
                                        ->createOptionForm([
                                            TextInput::make('sku')
                                                ->required()
                                                ->label('Product Number')
                                                ->maxLength(255),
                                            TextInput::make('name')
                                                ->label('Product Name')
                                                ->required()
                                                ->maxLength(255),
                                            TextInput::make('description')
                                                ->maxLength(255),
                                            TextInput::make('price')
                                                ->required()
                                                ->numeric()
                                                ->default(0)
                                                ->prefix('$')
                                                ->label('Price'),
                                            TextInput::make('stock')
                                                ->required()
                                                ->numeric()
                                                ->default(0)
                                                ->label('Stock'),
                                        ])
                                        ->createOptionUsing(function (array $data): int {
                                            return Product::create($data)->getKey();
                                        })
                                        ->createOptionAction(function (Action $action) {
                                            return $action
                                                ->modalHeading('Create new product')
                                                ->modalWidth('lg');
                                        })
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
                                        ->required(fn(callable $get) => !$get('is_custom_product'))
                                        ->reactive()
                                        ->searchable()
                                        ->visible(fn(callable $get) => !$get('is_custom_product'))
                                        ->getSearchResultsUsing(function (string $search): array {
                                            return Product::query()
                                                ->active()
                                                ->where(function ($query) use ($search) {
                                                    $query->where('name', 'ilike', "%{$search}%")
                                                        ->orWhere('sku', 'ilike', "%{$search}%");
                                                })
                                                ->limit(50)
                                                ->get()
                                                ->mapWithKeys(fn(Product $product) => [
                                                    $product->id => view('filament.components.product-option', [
                                                        'sku' => $product->sku,
                                                        'name' => $product->name,
                                                    ])->render()
                                                ])
                                                ->toArray();
                                        })
                                        ->afterStateUpdated(
                                            fn($state, callable $set) =>
                                            $set('price', Product::find($state)?->price ?? 0)
                                        )
                                        ->live('blur'),
                                    TextInput::make('custom_description')
                                        ->label('Custom Product Description')
                                        ->required(fn(callable $get) => $get('is_custom_product'))
                                        ->visible(fn(callable $get) => $get('is_custom_product'))
                                        ->columnSpan(6)
                                        ->live('blur'),
                                    Toggle::make('fill_load')
                                        ->label('Fill load')
                                        ->columnSpan(2)
                                        ->inline(false)
                                        ->reactive()
                                        ->afterStateUpdated(function ($state, callable $set) {
                                            if ($state) {
                                                $set('quantity', null);
                                            }
                                        })
                                        ->live('blur'),
                                    TextInput::make('quantity')
                                        ->numeric()
                                        ->columnSpan(2)
                                        ->default(1)
                                        ->disabled(fn(Get $get): bool => $get('fill_load'))
                                        ->dehydrated(fn(Get $get): bool => !$get('fill_load'))
                                        ->live('blur'),
                                    TextInput::make('quantity_delivered')
                                        ->label('Delivered')
                                        ->columnSpan(1)
                                        ->numeric()
                                        ->disabled(function (Get $get): bool {
                                            $status = $get('../../status');
                                            return !in_array($status, [
                                                OrderStatus::DELIVERED->value,
                                                OrderStatus::INVOICED->value,
                                                OrderStatus::COMPLETED->value,
                                                OrderStatus::PICKED_UP->value,
                                                OrderStatus::TRANSFERRED->value,
                                                OrderStatus::PREBURY_DELIVERED->value,
                                            ]);
                                        }),
                                ]),
                            Hidden::make('price')->default(0),
                            Grid::make(12)
                                ->schema([
                                    TextInput::make('location')
                                        ->label('Location')
                                        ->live()
                                        ->nullable()
                                        ->columnSpan([
                                            'default' => 12,
                                            'lg' => 6,
                                        ]),
                                    TextInput::make('notes')
                                        ->label('Notes')
                                        ->live()
                                        ->nullable()
                                        ->columnSpan([
                                            'default' => 12,
                                            'lg' => 6,
                                        ]),
                                ]),
                        ])
                        ->columns(12)
                        ->columnSpanFull()
                        ->itemLabel(function ($state) {
                            // If fill_load is true, show "Fill load" instead of quantity
                            $quantityLabel = (!empty($state['fill_load'])) ? 'Fill load' : ($state['quantity'] ?? 1);

                            // If using custom product, use the custom description
                            if (($state['is_custom_product'] ?? false) && !empty($state['custom_description'])) {
                                return "{$quantityLabel} x Custom - {$state['custom_description']}";
                            }

                            // Otherwise, look up the product by product_id
                            $sku = '';
                            $name = '';
                            if (!empty($state['product_id'])) {
                                $product = Product::find($state['product_id']);
                                if ($product) {
                                    $sku = $product->sku;
                                    $name = $product->name;
                                }
                            }
                            return "{$quantityLabel} x {$sku}" . ($name ? " - {$name}" : '');
                        }),
                ]),
        ];
    }
}
