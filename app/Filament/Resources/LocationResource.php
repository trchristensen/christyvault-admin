<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LocationResource\Pages;
use App\Models\Location;
use App\Models\Contact;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Forms\Components\RichEditor;
use Ysfkaya\FilamentPhoneInput\Forms\PhoneInput;

class LocationResource extends Resource
{
    protected static ?string $model = Location::class;

    protected static ?string $navigationIcon = 'heroicon-o-map-pin';
    protected static ?string $navigationGroup = 'Directories';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Basic Information')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\Select::make('location_type')
                            ->options([
                                'business' => 'Business',
                                'residential' => 'Residential',
                                'funeral_home' => 'Funeral Home',
                                'cemetery' => 'Cemetery',
                                'other' => 'Other',
                            ])
                            ->required()
                            ->native(false),
                        PhoneInput::make('phone')
                            ->label('General Phone Number')
                            ->helperText('This is the general phone number, not just for deliveries. Please make a contact for specifics like deliveries.')
                            ->defaultCountry('US'),
                        Forms\Components\TextInput::make('email')
                            ->label('General Email')
                            ->email(),
                        Forms\Components\Select::make('preferred_delivery_contact_id')
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
                                Forms\Components\TextInput::make('name')
                                    ->required()
                                    ->maxLength(255),
                                Forms\Components\TextInput::make('email')
                                    ->email()
                                    ->maxLength(255),
                                Forms\Components\Grid::make(2)
                                    ->schema([
                                        PhoneInput::make('phone')
                                            ->label('Office Phone')
                                            ->defaultCountry('US'),
                                        Forms\Components\TextInput::make('phone_extension')
                                            ->label('Extension')
                                            ->maxLength(10)
                                            ->placeholder('x1234'),
                                    ]),
                                PhoneInput::make('mobile_phone')
                                    ->label('Mobile Phone')
                                    ->defaultCountry('US'),
                                Forms\Components\Select::make('contact_types')
                                    ->relationship('contactTypes', 'name')
                                    ->multiple()
                                    ->preload()
                                    ->required()
                                    ->native(false),
                            ])
                            ->editOptionForm([
                                Forms\Components\TextInput::make('name')
                                    ->required()
                                    ->maxLength(255),
                                Forms\Components\TextInput::make('email')
                                    ->email()
                                    ->maxLength(255),
                                Forms\Components\Grid::make(2)
                                    ->schema([
                                        PhoneInput::make('phone')
                                            ->label('Office Phone')
                                            ->defaultCountry('US'),
                                        Forms\Components\TextInput::make('phone_extension')
                                            ->label('Extension')
                                            ->maxLength(10)
                                            ->placeholder('x1234'),
                                    ]),
                                PhoneInput::make('mobile_phone')
                                    ->label('Mobile Phone')
                                    ->defaultCountry('US'),
                                Forms\Components\Select::make('contact_types')
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

                Forms\Components\Section::make('Address Details')
                    ->schema([
                        Forms\Components\TextInput::make('address_line1')
                            ->label('Address Line 1')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('address_line2')
                            ->label('Address Line 2')
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
                    ])->columns(2),

                Forms\Components\Section::make('Notes')
                    ->schema([
                        Forms\Components\MarkdownEditor::make('notes')
                    ])
                    ->columns(1),
                Forms\Components\Section::make('Coordinates')
                    ->schema([
                        Forms\Components\TextInput::make('latitude')
                            ->numeric()
                            ->rules(['nullable', 'numeric', 'between:-90,90'])
                            ->step(0.000000000001)
                            ->placeholder('e.g. 37.957702'),
                        Forms\Components\TextInput::make('longitude')
                            ->numeric()
                            ->rules(['nullable', 'numeric', 'between:-180,180'])
                            ->step(0.000000000001)
                            ->placeholder('e.g. -121.290780'),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('location_type')
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
                Tables\Columns\TextColumn::make('city')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('state')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('order_status')
                    ->badge()
                    ->color(fn(Location $record): string => $record->order_status_color),
                Tables\Columns\TextColumn::make('last_order_at')
                    ->label('Last order at')
                    ->formatStateUsing(function ($state) {
                        if (!$state) return 'N/A';
                        try {
                            $date = \Carbon\Carbon::parse($state);
                            $daysAgo = intval($date->diffInDays());
                            $dateString = $date->format('n/j');
                            return $daysAgo === 1
                                ? "1 day ago ({$dateString})"
                                : "{$daysAgo} days ago " . "\n" . "({$dateString})";
                        } catch (\Exception $e) {
                            return $state;
                        }
                    })
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('average_order_frequency_days')
                    ->label('Avg. Order Frequency')
                    ->formatStateUsing(fn($state) => $state ? "{$state} days" : 'N/A')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('total_orders')
                    ->label('Total Orders')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('common_order_items')
                    ->label('Common Items')
                    ->formatStateUsing(function ($state) {
                        // Always decode as JSON, no matter what
                        $array = json_decode($state, true);
                        if (!is_array($array) || empty($array)) return 'N/A';

                        // Convert associative arrays (product_id as key) to indexed arrays
                        $array = array_values($array);

                        return collect($array)
                            ->take(3)
                            ->map(function ($item) {
                                if (is_array($item) && isset($item['sku'], $item['count'])) {
                                    return "{$item['count']} x {$item['sku']}";
                                }
                                return '';
                            })
                            ->filter()
                            ->join("<br>"); // Each item on its own line, no commas
                    })
                    ->html()
                    ->wrap()
                    ->toggleable(),
            ])
            ->defaultSort('last_order_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('order_status')
                    ->options([
                        'No Orders' => 'No Orders',
                        'New Customer' => 'New Customer',
                        'Overdue' => 'Overdue',
                        'Due Soon' => 'Due Soon',
                        'Recently Ordered' => 'Recently Ordered',
                    ]),
                Tables\Filters\SelectFilter::make('location_type')
                    ->options([
                        'business' => 'Business',
                        'residential' => 'Residential',
                        'funeral_home' => 'Funeral Home',
                        'cemetery' => 'Cemetery',
                        'other' => 'Other',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
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
            'index' => Pages\ListLocations::route('/'),
            'create' => Pages\CreateLocation::route('/create'),
            'view' => Pages\ViewLocation::route('/{record}'),
            'edit' => Pages\EditLocation::route('/{record}/edit'),
        ];
    }
}
