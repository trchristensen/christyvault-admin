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
                                    $optionLabel = view('filament.components.contact-option', [
                                        'contact' => $contact,
                                    ])->render();

                                    if ($linkedContactIds->contains($contact->id)) {
                                        $groupedContacts['Linked Contacts'][$contact->id] = $optionLabel;
                                    } else {
                                        $groupedContacts['Other Contacts'][$contact->id] = $optionLabel;
                                    }
                                }

                                // Remove empty groups
                                if (empty($groupedContacts['Linked Contacts'])) {
                                    unset($groupedContacts['Linked Contacts']);
                                }
                                if (empty($groupedContacts['Other Contacts'])) {
                                    unset($groupedContacts['Other Contacts']);
                                }

                                return $groupedContacts;
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
                Tables\Columns\TextColumn::make('full_address')
                    ->label('Address')
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->where('address_line1', 'like', "%{$search}%")
                            ->orWhere('city', 'like', "%{$search}%")
                            ->orWhere('state', 'like', "%{$search}%")
                            ->orWhere('postal_code', 'like', "%{$search}%");
                    }),
                Tables\Columns\TextColumn::make('coordinates')
                    ->label('Coordinates')
                    ->getStateUsing(
                        fn($record): string =>
                        $record->hasCoordinates()
                            ? "{$record->latitude}, {$record->longitude}"
                            : 'Not set'
                    )
                    ->searchable(['latitude', 'longitude']),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('location_type')
                    ->options([
                        'cemetery' => 'Cemetery',
                        'section' => 'Cemetery Section',  // for specific yards/sections
                        'funeral_home' => 'Funeral Home',
                        'other' => 'Other',
                    ])
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
