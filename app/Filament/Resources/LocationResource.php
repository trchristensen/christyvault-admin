<?php

namespace App\Filament\Resources;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Grid;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Placeholder;
use Filament\Tables\Columns\TextColumn;
use Carbon\Carbon;
use Exception;
use Filament\Tables\Filters\SelectFilter;
use Filament\Actions\ViewAction;
use Filament\Actions\EditAction;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Resources\LocationResource\RelationManagers\OrderedProductsRelationManager;
use App\Filament\Resources\LocationResource\RelationManagers\OrdersRelationManager;
use App\Filament\Resources\LocationResource\RelationManagers\NearbyLocationsRelationManager;
use App\Filament\Resources\LocationResource\Pages\ListLocations;
use App\Filament\Resources\LocationResource\Pages\CreateLocation;
use App\Filament\Resources\LocationResource\Pages\ViewLocation;
use App\Filament\Resources\LocationResource\Pages\EditLocation;
use App\Enums\PlantLocation;
use App\Filament\Resources\LocationResource\Pages;
use App\Filament\Resources\LocationResource\RelationManagers;
use App\Models\Location;
use App\Models\Contact;
use App\Services\LocationGeocodingService;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Forms\Components\RichEditor;
use Ysfkaya\FilamentPhoneInput\Forms\PhoneInput;
use Throwable;

class LocationResource extends Resource
{
    protected static ?string $model = Location::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-map-pin';
    protected static string | \UnitEnum | null $navigationGroup = 'Directories';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Basic Information')
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        Select::make('location_type')
                            ->options([
                                'business' => 'Business',
                                'residential' => 'Residential',
                                'funeral_home' => 'Funeral Home',
                                'cemetery' => 'Cemetery',
                                'other' => 'Other',
                            ])
                            ->required()
                            ->native(false),
                        Select::make('default_plant_location')
                            ->label('Default Delivery Type')
                            ->options(
                                collect(PlantLocation::cases())
                                    ->mapWithKeys(fn(PlantLocation $location) => [
                                        $location->value => $location->getLabel(),
                                    ])
                                    ->toArray()
                            )
                            ->default(PlantLocation::COLMA_MAIN->value)
                            ->required()
                            ->native(false),
                        PhoneInput::make('phone')
                            ->label('General Phone Number')
                            ->helperText('This is the general phone number, not just for deliveries. Please make a contact for specifics like deliveries.')
                            ->defaultCountry('US'),
                        TextInput::make('email')
                            ->label('General Email')
                            ->email(),
                        Select::make('preferred_delivery_contact_id')
                            ->label('Preferred contact for delivery')
                            ->relationship(
                                'preferredDeliveryContact',
                                'name'
                            )
                            ->options(function (?Location $record) {
                                // Get all contacts
                                $contacts = Contact::orderBy('name')->get();

                                // If we're editing an existing location, get its linked contacts
                                $linkedContactIds = $record ? $record->contacts()->pluck('contacts.id') : collect();

                                // Group the contacts
                                $groupedContacts = [
                                    'Linked Contacts' => [],
                                    'Other Contacts' => [],
                                ];

                                foreach ($contacts as $contact) {
                                    // Build the HTML for the contact option
                                    $html = "<div class='font-medium'>{$contact->name}</div>";

                                    // Phone numbers line
                                    $phoneInfo = [];
                                    if ($contact->phone) {
                                        $phoneInfo[] = $contact->phone . ($contact->phone_extension ? " x{$contact->phone_extension}" : '');
                                    }
                                    if ($contact->mobile_phone) {
                                        $phoneInfo[] = "Mobile: {$contact->mobile_phone}";
                                    }
                                    if (!empty($phoneInfo)) {
                                        $html .= "<div class='text-sm text-gray-500'>" . implode(' • ', $phoneInfo) . "</div>";
                                    }

                                    // Linked locations line
                                    if ($contact->locations->isNotEmpty()) {
                                        $locationInfo = $contact->locations->map(fn($loc) => "{$loc->name} ({$loc->city})")->join(', ');
                                        $html .= "<div class='mt-1 text-xs text-gray-400'>Linked to: {$locationInfo}</div>";
                                    }

                                    // Add to appropriate group
                                    if ($linkedContactIds->contains($contact->id)) {
                                        $groupedContacts['Linked Contacts'][$contact->id] = $html;
                                    } else {
                                        $groupedContacts['Other Contacts'][$contact->id] = $html;
                                    }
                                }

                                // Remove empty groups
                                return array_filter($groupedContacts, fn($group) => !empty($group));
                            })
                            ->afterStateUpdated(function ($state, $record, $set, $get) {
                                if (!$state) return;

                                $contact = Contact::find($state);
                                if (!$contact) return;

                                if ($record) {
                                    if (!$record->contacts()->where('contacts.id', $contact->id)->exists()) {
                                        $record->contacts()->attach($contact);
                                        $record->refresh();
                                    }
                                } else {
                                    $set('_temp_contact_to_link', $state);
                                }
                            })
                            ->createOptionForm([
                                TextInput::make('name')
                                    ->required()
                                    ->maxLength(255),
                                TextInput::make('email')
                                    ->email()
                                    ->maxLength(255),
                                Grid::make(2)
                                    ->schema([
                                        PhoneInput::make('phone')
                                            ->label('Office Phone')
                                            ->defaultCountry('US'),
                                        TextInput::make('phone_extension')
                                            ->label('Extension')
                                            ->maxLength(10)
                                            ->placeholder('x1234'),
                                    ]),
                                PhoneInput::make('mobile_phone')
                                    ->label('Mobile Phone')
                                    ->defaultCountry('US'),
                                Select::make('contact_types')
                                    ->relationship('contactTypes', 'name')
                                    ->multiple()
                                    ->preload()
                                    ->required()
                                    ->native(false),
                            ])
                            ->editOptionForm([
                                TextInput::make('name')
                                    ->required()
                                    ->maxLength(255),
                                TextInput::make('email')
                                    ->email()
                                    ->maxLength(255),
                                Grid::make(2)
                                    ->schema([
                                        PhoneInput::make('phone')
                                            ->label('Office Phone')
                                            ->defaultCountry('US'),
                                        TextInput::make('phone_extension')
                                            ->label('Extension')
                                            ->maxLength(10)
                                            ->placeholder('x1234'),
                                    ]),
                                PhoneInput::make('mobile_phone')
                                    ->label('Mobile Phone')
                                    ->defaultCountry('US'),
                                Select::make('contact_types')
                                    ->relationship('contactTypes', 'name')
                                    ->multiple()
                                    ->preload()
                                    ->required()
                                    ->native(false),
                            ])
                            ->searchable()
                            ->preload()
                            ->allowHtml(),
                    ])->columns(2),

                Section::make('Address Details')
                    ->schema([
                        TextInput::make('address_line1')
                            ->label('Address Line 1')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('address_line2')
                            ->label('Address Line 2')
                            ->maxLength(255),
                        TextInput::make('city')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('state')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('postal_code')
                            ->required()
                            ->maxLength(20),
                    ])->columns(2),

                Section::make('Notes')
                    ->schema([
                        MarkdownEditor::make('notes')
                    ])
                    ->columns(1),
                Section::make('Coordinates')
                    ->schema([
                        TextInput::make('latitude')
                            ->numeric()
                            ->rules(['nullable', 'numeric', 'between:-90,90'])
                            ->step(0.000000000001)
                            ->placeholder('e.g. 37.957702'),
                        TextInput::make('longitude')
                            ->numeric()
                            ->rules(['nullable', 'numeric', 'between:-180,180'])
                            ->step(0.000000000001)
                            ->placeholder('e.g. -121.290780'),
                        Placeholder::make('geocoded_at_display')
                            ->label('Geocoded')
                            ->content(fn(?Location $record): string => $record?->geocoded_at
                                ? $record->geocoded_at->format('M j, Y g:i A')
                                : 'Not geocoded'),
                        Placeholder::make('geocoding_provider_display')
                            ->label('Provider')
                            ->content(fn(?Location $record): string => $record?->geocoding_provider ?? 'N/A'),
                        Placeholder::make('geocoding_matched_address_display')
                            ->label('Matched Address')
                            ->content(fn(?Location $record): string => $record?->geocoding_matched_address ?? 'N/A')
                            ->columnSpanFull(),
                        Placeholder::make('geocoding_failure_display')
                            ->label('Last Geocoding Failure')
                            ->content(fn(?Location $record): string => $record?->geocoding_failure_reason
                                ? "{$record->geocoding_failure_reason} ({$record->geocoding_failed_at?->format('M j, Y g:i A')})"
                                : 'None')
                            ->columnSpanFull(),
                    ])->columns(2),
                Section::make('Default Plant Drive Distance')
                    ->schema([
                        Placeholder::make('plant_drive_distance_origin')
                            ->label('Plant')
                            ->content(fn(?Location $record): string => $record?->plantDriveDistanceOrigin?->name ?? 'Not calculated'),
                        Placeholder::make('plant_drive_distance_miles_display')
                            ->label('Drive Distance')
                            ->content(fn(?Location $record): string => $record?->plant_drive_distance_miles !== null
                                ? number_format((float) $record->plant_drive_distance_miles, 1) . ' mi'
                                : 'Not calculated'),
                        Placeholder::make('plant_drive_duration_display')
                            ->label('Drive Time')
                            ->content(fn(?Location $record): string => $record?->plant_drive_duration_minutes !== null
                                ? "{$record->plant_drive_duration_minutes} min"
                                : 'Not calculated'),
                        Placeholder::make('current_delivery_rate_display')
                            ->label('Delivery Rate')
                            ->content(fn(?Location $record): string => $record?->current_delivery_rate_summary ?? 'Not calculated'),
                        Placeholder::make('plant_drive_distance_calculated_at_display')
                            ->label('Calculated')
                            ->content(fn(?Location $record): string => $record?->plant_drive_distance_calculated_at
                                ? $record->plant_drive_distance_calculated_at->format('M j, Y g:i A')
                                : 'Not calculated'),
                    ])
                    ->columns(5)
                    ->visible(fn(?Location $record): bool => $record !== null),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('location_type')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'business' => 'info',
                        'residential' => 'success',
                        'funeral_home' => 'warning',
                        'cemetery' => 'danger',
                        default => 'gray',
                    })
                    ->searchable()
                    ->sortable(),
                TextColumn::make('default_plant_location')
                    ->label('Default Delivery Type')
                    ->badge()
                    ->formatStateUsing(function ($state): string {
                        if ($state instanceof PlantLocation) {
                            return $state->getLabel();
                        }

                        return PlantLocation::tryFrom((string) $state)?->getLabel() ?? 'Colma';
                    })
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('city')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('state')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('order_status')
                    ->badge()
                    ->color(fn(Location $record): string => $record->order_status_color),
                TextColumn::make('last_order_at')
                    ->label('Last order at')
                    ->formatStateUsing(function ($state) {
                        if (!$state) return 'N/A';
                        try {
                            $date = Carbon::parse($state);
                            $daysAgo = intval($date->diffInDays(now(), true));
                            $dateString = $date->format('n/j');
                            return $daysAgo === 1
                                ? "1 day ago ({$dateString})"
                                : "{$daysAgo} days ago " . "\n" . "({$dateString})";
                        } catch (Exception $e) {
                            return $state;
                        }
                    })
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('average_order_frequency_days')
                    ->label('Avg. Order Frequency')
                    ->formatStateUsing(fn($state) => $state ? "{$state} days" : 'N/A')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('total_orders')
                    ->label('Total Orders')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('plant_drive_distance_miles')
                    ->label('Plant Drive Miles')
                    ->formatStateUsing(fn($state): string => $state !== null ? number_format((float) $state, 1) . ' mi' : 'N/A')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('current_delivery_rate_summary')
                    ->label('Delivery Rate')
                    ->badge()
                    ->placeholder('N/A')
                    ->toggleable(isToggledHiddenByDefault: true),
                // TextColumn::make('common_order_items')
                //     ->label('Common Items')
                //     ->formatStateUsing(function ($state) {
                //         // Always decode as JSON, no matter what
                //         $array = json_decode($state, true);
                //         if (!is_array($array) || empty($array)) return 'N/A';

                //         // Convert associative arrays (product_id as key) to indexed arrays
                //         $array = array_values($array);

                //         return collect($array)
                //             ->take(3)
                //             ->map(function ($item) {
                //                 if (is_array($item) && isset($item['sku'], $item['count'])) {
                //                     return "{$item['count']} x {$item['sku']}";
                //                 }
                //                 return '';
                //             })
                //             ->filter()
                //             ->join("<br>"); // Each item on its own line, no commas
                //     })
                //     ->html()
                //     ->wrap()
                //     ->toggleable(),
            ])
            ->defaultSort('last_order_at', 'desc')
            ->filters([
                SelectFilter::make('order_status')
                    ->label('Order Status')
                    ->options([
                        'No Orders' => 'No Orders',
                        'New Customer' => 'New Customer',
                        'Overdue' => 'Overdue',
                        'Due Soon' => 'Due Soon',
                        'Recently Ordered' => 'Recently Ordered',
                    ])
                    ->multiple()
                    ->native(false)
                    ->query(function (Builder $query, array $data): Builder {
                        $statuses = $data['values'] ?? [];

                        if ($statuses === []) {
                            return $query;
                        }

                        $daysSinceLastOrder = 'ABS(EXTRACT(EPOCH FROM (CURRENT_TIMESTAMP - locations.last_order_at)) / 86400)';

                        return $query->where(function (Builder $query) use ($statuses, $daysSinceLastOrder): void {
                            foreach ($statuses as $status) {
                                $query->orWhere(function (Builder $query) use ($status, $daysSinceLastOrder): void {
                                    match ($status) {
                                        'No Orders' => $query->whereNull('locations.last_order_at'),
                                        'New Customer' => $query
                                            ->whereNotNull('locations.last_order_at')
                                            ->where(function (Builder $query): void {
                                                $query->whereNull('locations.average_order_frequency_days')
                                                    ->orWhere('locations.average_order_frequency_days', '<=', 0);
                                            }),
                                        'Overdue' => $query
                                            ->whereNotNull('locations.last_order_at')
                                            ->where('locations.average_order_frequency_days', '>', 0)
                                            ->whereRaw("{$daysSinceLastOrder} > locations.average_order_frequency_days * 1.5"),
                                        'Due Soon' => $query
                                            ->whereNotNull('locations.last_order_at')
                                            ->where('locations.average_order_frequency_days', '>', 0)
                                            ->whereRaw("{$daysSinceLastOrder} > locations.average_order_frequency_days")
                                            ->whereRaw("{$daysSinceLastOrder} <= locations.average_order_frequency_days * 1.5"),
                                        'Recently Ordered' => $query
                                            ->whereNotNull('locations.last_order_at')
                                            ->where('locations.average_order_frequency_days', '>', 0)
                                            ->whereRaw("{$daysSinceLastOrder} <= locations.average_order_frequency_days"),
                                        default => null,
                                    };
                                });
                            }
                        });
                    }),
                SelectFilter::make('location_type')
                    ->options([
                        'business' => 'Business',
                        'residential' => 'Residential',
                        'funeral_home' => 'Funeral Home',
                        'cemetery' => 'Cemetery',
                        'other' => 'Other',
                    ]),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                Action::make('geocode')
                    ->label('Geocode')
                    ->icon('heroicon-o-map-pin')
                    ->requiresConfirmation()
                    ->action(function (Location $record): void {
                        if (! $record->hasAddressForGeocoding()) {
                            Notification::make()
                                ->title('Address is incomplete')
                                ->body('Address line 1, city, and state are required before geocoding.')
                                ->danger()
                                ->send();

                            return;
                        }

                        try {
                            $result = app(LocationGeocodingService::class)->geocodeLocation($record);
                        } catch (Throwable $exception) {
                            $record->forceFill([
                                'geocoding_failed_at' => now(),
                                'geocoding_failure_reason' => str($exception->getMessage())->limit(255)->toString(),
                            ])->saveQuietly();

                            Notification::make()
                                ->title('Geocoding failed')
                                ->body($exception->getMessage())
                                ->danger()
                                ->send();

                            return;
                        }

                        if (! $result) {
                            $record->forceFill([
                                'geocoding_failed_at' => now(),
                                'geocoding_failure_reason' => 'No Census geocoder match found.',
                            ])->saveQuietly();

                            Notification::make()
                                ->title('No geocoding match found')
                                ->body('Check the address and try again.')
                                ->danger()
                                ->send();

                            return;
                        }

                        $record->clearPlantDriveDistance();

                        $record->forceFill([
                            'latitude' => $result['latitude'],
                            'longitude' => $result['longitude'],
                            'geocoding_provider' => $result['provider'],
                            'geocoding_matched_address' => $result['matched_address'],
                            'geocoded_at' => now(),
                            'geocoding_failed_at' => null,
                            'geocoding_failure_reason' => null,
                        ])->saveQuietly();

                        Notification::make()
                            ->title('Location geocoded')
                            ->body($result['matched_address'] ?? "{$result['latitude']}, {$result['longitude']}")
                            ->success()
                            ->send();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            OrderedProductsRelationManager::class,
            OrdersRelationManager::class,
            NearbyLocationsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLocations::route('/'),
            'create' => CreateLocation::route('/create'),
            'view' => ViewLocation::route('/{record}'),
            'edit' => EditLocation::route('/{record}/edit'),
        ];
    }
}
