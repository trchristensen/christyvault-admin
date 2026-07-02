<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DeliveryRateResource\Pages;
use App\Models\DeliveryRate;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class DeliveryRateResource extends Resource
{
    protected static ?string $model = DeliveryRate::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = 'Delivery Management';

    protected static ?string $navigationLabel = 'Delivery Rates';

    protected static ?int $navigationSort = 30;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Delivery Rate')
                    ->schema([
                        Forms\Components\DatePicker::make('effective_date')
                            ->required()
                            ->native(false),
                        Forms\Components\TextInput::make('zone')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('min_miles')
                            ->label('Minimum Miles')
                            ->numeric()
                            ->required()
                            ->minValue(0),
                        Forms\Components\TextInput::make('max_miles')
                            ->label('Maximum Miles')
                            ->numeric()
                            ->minValue(0),
                        Forms\Components\TextInput::make('miles_label')
                            ->label('Displayed Miles')
                            ->helperText('Example: 51-100')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('price')
                            ->numeric()
                            ->required()
                            ->prefix('$')
                            ->minValue(0),
                        Forms\Components\Select::make('price_unit')
                            ->label('Calculation')
                            ->options([
                                DeliveryRate::UNIT_VAULT => 'Per vault',
                                DeliveryRate::UNIT_DELIVERY => 'Flat delivery rate',
                            ])
                            ->default(DeliveryRate::UNIT_DELIVERY)
                            ->required()
                            ->native(false),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('effective_date')
                    ->date('M j, Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('zone')
                    ->badge()
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('miles_range_label')
                    ->label('Miles'),
                Tables\Columns\TextColumn::make('price')
                    ->money('USD')
                    ->sortable(),
                Tables\Columns\TextColumn::make('calculation_label')
                    ->label('Calculation'),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('effective_date', 'desc')
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDeliveryRates::route('/'),
            'create' => Pages\CreateDeliveryRate::route('/create'),
            'edit' => Pages\EditDeliveryRate::route('/{record}/edit'),
        ];
    }
}
