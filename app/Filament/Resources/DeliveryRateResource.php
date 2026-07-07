<?php

namespace App\Filament\Resources;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\TextColumn;
use Filament\Actions\EditAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Resources\DeliveryRateResource\Pages\ListDeliveryRates;
use App\Filament\Resources\DeliveryRateResource\Pages\CreateDeliveryRate;
use App\Filament\Resources\DeliveryRateResource\Pages\EditDeliveryRate;
use App\Filament\Resources\DeliveryRateResource\Pages;
use App\Models\DeliveryRate;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class DeliveryRateResource extends Resource
{
    protected static ?string $model = DeliveryRate::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-banknotes';

    protected static string | \UnitEnum | null $navigationGroup = 'Delivery Management';

    protected static ?string $navigationLabel = 'Delivery Rates';

    protected static ?int $navigationSort = 30;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Delivery Rate')
                    ->schema([
                        DatePicker::make('effective_date')
                            ->required()
                            ->native(false),
                        TextInput::make('zone')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('min_miles')
                            ->label('Minimum Miles')
                            ->numeric()
                            ->required()
                            ->minValue(0),
                        TextInput::make('max_miles')
                            ->label('Maximum Miles')
                            ->numeric()
                            ->minValue(0),
                        TextInput::make('miles_label')
                            ->label('Displayed Miles')
                            ->helperText('Example: 51-100')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('price')
                            ->numeric()
                            ->required()
                            ->prefix('$')
                            ->minValue(0),
                        Select::make('price_unit')
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
                TextColumn::make('effective_date')
                    ->date('M j, Y')
                    ->sortable(),
                TextColumn::make('zone')
                    ->badge()
                    ->searchable()
                    ->sortable(),
                TextColumn::make('miles_range_label')
                    ->label('Miles'),
                TextColumn::make('price')
                    ->money('USD')
                    ->sortable(),
                TextColumn::make('calculation_label')
                    ->label('Calculation'),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('effective_date', 'desc')
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDeliveryRates::route('/'),
            'create' => CreateDeliveryRate::route('/create'),
            'edit' => EditDeliveryRate::route('/{record}/edit'),
        ];
    }
}
