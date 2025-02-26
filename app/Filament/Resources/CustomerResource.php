<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CustomerResource\Pages;
use App\Filament\Resources\CustomerResource\RelationManagers;
use App\Models\Customer;
use App\Models\Location;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Ysfkaya\FilamentPhoneInput\Forms\PhoneInput;
use Ysfkaya\FilamentPhoneInput\PhoneInputNumberType;
use Ysfkaya\FilamentPhoneInput\Tables\PhoneColumn;

class CustomerResource extends Resource
{
    protected static ?string $model = Customer::class;

    protected static ?string $navigationGroup = 'Directories';

    protected static ?string $navigationIcon = 'heroicon-o-building-office';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Customer Information')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('email')
                            ->email()
                            ->maxLength(255),
                        PhoneInput::make('phone')->defaultCountry('US'),
                        Forms\Components\TextInput::make('contact_name')
                            ->label('Contact Name')
                            ->maxLength(255),
                    ]),

                Forms\Components\Section::make('Location')
                    ->schema([
                        Forms\Components\Select::make('location.location_type')
                            ->options([
                                'business' => 'Business',
                                'residential' => 'Residential',
                                'funeral_home' => 'Funeral Home',
                                'cemetery' => 'Cemetery',
                                'other' => 'Other',
                            ])
                            ->default('cemetery')
                            ->required()
                            ->native(false)
                            ->afterStateHydrated(function ($component, $state, $record) {
                                if ($record) {
                                    $location = $record->locations()->first();
                                    if ($location) {
                                        $component->state($location->location_type);
                                    }
                                }
                            }),
                        Forms\Components\TextInput::make('location.address_line1')
                            ->label('Address Line 1')
                            ->required()
                            ->maxLength(255)
                            ->afterStateHydrated(function ($component, $state, $record) {
                                if ($record) {
                                    $location = $record->locations()->first();
                                    if ($location) {
                                        $component->state($location->address_line1);
                                    }
                                }
                            }),
                        Forms\Components\TextInput::make('location.address_line2')
                            ->label('Address Line 2')
                            ->maxLength(255)
                            ->afterStateHydrated(function ($component, $state, $record) {
                                if ($record && $record->locations()->first()) {
                                    $component->state($record->locations()->first()->address_line2);
                                }
                            }),
                        Forms\Components\TextInput::make('location.city')
                            ->required()
                            ->maxLength(255)
                            ->afterStateHydrated(function ($component, $state, $record) {
                                if ($record && $record->locations()->first()) {
                                    $component->state($record->locations()->first()->city);
                                }
                            }),
                        Forms\Components\TextInput::make('location.state')
                            ->required()
                            ->maxLength(255)
                            ->afterStateHydrated(function ($component, $state, $record) {
                                if ($record && $record->locations()->first()) {
                                    $component->state($record->locations()->first()->state);
                                }
                            }),
                        Forms\Components\TextInput::make('location.postal_code')
                            ->required()
                            ->maxLength(20)
                            ->afterStateHydrated(function ($component, $state, $record) {
                                if ($record && $record->locations()->first()) {
                                    $component->state($record->locations()->first()->postal_code);
                                }
                            }),
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('location.latitude')
                                    ->numeric()
                                    ->rules(['nullable', 'numeric', 'between:-90,90'])
                                    ->step(0.000000000001)
                                    ->afterStateHydrated(function ($component, $state, $record) {
                                        if ($record) {
                                            $location = $record->locations()->first();
                                            if ($location) {
                                                $component->state($location->latitude);
                                            }
                                        }
                                    })
                                    ->placeholder('e.g. 37.957702'),
                                Forms\Components\TextInput::make('location.longitude')
                                    ->numeric()
                                    ->rules(['nullable', 'numeric', 'between:-180,180'])
                                    ->step(0.000000000001)
                                    ->afterStateHydrated(function ($component, $state, $record) {
                                        if ($record) {
                                            $location = $record->locations()->first();
                                            if ($location) {
                                                $component->state($location->longitude);
                                            }
                                        }
                                    })
                                    ->placeholder('e.g. -121.290780'),
                            ]),
                    ])->columns(2),
            ])
            ->statePath('data');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('email')
                    ->searchable(),
                PhoneColumn::make('phone')
                    ->displayFormat(PhoneInputNumberType::INTERNATIONAL)
                    ->searchable(),
                Tables\Columns\TextColumn::make('locations.address_line1')
                    ->label('Primary Address')
                    ->getStateUsing(function (Customer $record) {
                        $primary = $record->primaryLocation();
                        return $primary ? $primary->full_address : '';
                    })
                    ->searchable(),

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
                //
            ])
            ->actions([
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
            // Remove the LocationsRelationManager
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCustomers::route('/'),
            'create' => Pages\CreateCustomer::route('/create'),
            'edit' => Pages\EditCustomer::route('/{record}/edit'),
        ];
    }
}
