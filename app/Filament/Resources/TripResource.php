<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TripResource\Pages;
use App\Filament\Resources\TripResource\RelationManagers;
use App\Models\Employee;
use App\Models\Location;
use App\Models\Trip;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class TripResource extends Resource
{
    protected static ?string $model = Trip::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                // ... existing basic fields ...

                Forms\Components\Section::make('Locations')
                    ->schema([
                        Forms\Components\Select::make('start_location')
                            ->relationship('locations')
                            ->label('Start Location')
                            ->options(Location::pluck('name', 'id'))
                            ->searchable()
                            ->required()
                            ->createOptionForm([
                                Forms\Components\TextInput::make('name'),
                                Forms\Components\TextInput::make('address_line1')->required(),
                                Forms\Components\TextInput::make('city')->required(),
                                Forms\Components\TextInput::make('state')->required(),
                                Forms\Components\TextInput::make('postal_code')->required(),
                                Forms\Components\Select::make('location_type')
                                    ->options([
                                        'funeral_home' => 'Funeral Home',
                                        'other' => 'Other',
                                    ])
                                    ->default('funeral_home'),
                            ]),

                        Forms\Components\Repeater::make('delivery_locations')
                            ->relationship('locations')
                            ->label('Delivery Locations')
                            ->schema([
                                Forms\Components\Select::make('location_id')
                                    ->label('Cemetery')
                                    ->options(Location::where('location_type', 'cemetery')->pluck('name', 'id'))
                                    ->searchable()
                                    ->required()
                                    ->createOptionForm([
                                        Forms\Components\TextInput::make('name')->required(),
                                        Forms\Components\TextInput::make('address_line1')->required(),
                                        Forms\Components\TextInput::make('city')->required(),
                                        Forms\Components\TextInput::make('state')->required(),
                                        Forms\Components\TextInput::make('postal_code')->required(),
                                        Forms\Components\Hidden::make('location_type')
                                            ->default('cemetery'),
                                    ]),
                                Forms\Components\TextInput::make('sequence')
                                    ->numeric()
                                    ->default(1)
                                    ->hidden(),
                            ])
                            ->defaultItems(1)
                            ->maxItems(2) // Limit to maximum 2 delivery locations
                            ->columnSpanFull(),
                    ]),
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
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'pending' => 'gray',
                        'in_progress' => 'warning',
                        'completed' => 'success',
                        'cancelled' => 'danger',
                    }),
                Tables\Columns\TextColumn::make('scheduled_date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('start_time')
                    ->time(),
                Tables\Columns\TextColumn::make('end_time')
                    ->time(),
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
            //
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
}
