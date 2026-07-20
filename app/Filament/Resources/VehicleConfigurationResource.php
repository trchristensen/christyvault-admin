<?php

namespace App\Filament\Resources;

use App\Filament\Resources\VehicleConfigurationResource\Pages\CreateVehicleConfiguration;
use App\Filament\Resources\VehicleConfigurationResource\Pages\EditVehicleConfiguration;
use App\Filament\Resources\VehicleConfigurationResource\Pages\ListVehicleConfigurations;
use App\Models\VehicleConfiguration;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class VehicleConfigurationResource extends Resource
{
    protected static ?string $model = VehicleConfiguration::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-truck';

    protected static string|\UnitEnum|null $navigationGroup = 'Delivery Management';

    protected static ?int $navigationSort = 42;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Vehicle Configuration')
                ->description('A selectable trip configuration, including whether the piggyback forklift occupies the rear of the trailer.')
                ->schema([
                    TextInput::make('name')
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->maxLength(255),
                    TextInput::make('code')
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->maxLength(255),
                    Select::make('configuration_type')
                        ->label('Vehicle Type')
                        ->options(VehicleConfiguration::typeOptions())
                        ->required()
                        ->live()
                        ->native(false),
                    Select::make('rack_spot_count')
                        ->label('Physical Trailer Racks')
                        ->options(VehicleConfiguration::rackTrailerCountOptions())
                        ->helperText('The movable rack stops allow exactly 8 or 10 physical racks; a rack trailer cannot run with fewer than 8.')
                        ->required(fn (Get $get): bool => $get('configuration_type') === VehicleConfiguration::TYPE_RACK_TRAILER)
                        ->visible(fn (Get $get): bool => $get('configuration_type') === VehicleConfiguration::TYPE_RACK_TRAILER)
                        ->native(false),
                    TextInput::make('flatbed_pallet_capacity')
                        ->label('Fallback Flatbed Pallet Spots')
                        ->helperText('Pallets prefer protected rack positions. These flatbed spots are used only when needed to fit the complete load.')
                        ->numeric()
                        ->integer()
                        ->default(0)
                        ->minValue(0)
                        ->required()
                        ->visible(fn (Get $get): bool => $get('configuration_type') === VehicleConfiguration::TYPE_RACK_TRAILER),
                    TextInput::make('max_product_weight_lbs')
                        ->label('Maximum Product Weight')
                        ->helperText('Product cargo only. Racks and the piggyback forklift are excluded.')
                        ->numeric()
                        ->minValue(1)
                        ->suffix('lb'),
                    Toggle::make('piggyback_forklift_onboard')
                        ->label('Piggyback Forklift Onboard')
                        ->visible(fn (Get $get): bool => $get('configuration_type') === VehicleConfiguration::TYPE_RACK_TRAILER),
                    Toggle::make('is_active')
                        ->label('Active')
                        ->default(true),
                    Textarea::make('notes')
                        ->columnSpanFull()
                        ->maxLength(65535),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('configuration_type')
                    ->label('Type')
                    ->formatStateUsing(fn (string $state): string => VehicleConfiguration::typeOptions()[$state] ?? $state)
                    ->badge(),
                TextColumn::make('rack_spot_count')
                    ->label('Physical Racks')
                    ->placeholder('No racks')
                    ->formatStateUsing(fn ($state): string => filled($state) ? $state.' racks' : 'No racks')
                    ->sortable(),
                TextColumn::make('flatbed_pallet_capacity')
                    ->label('Flatbed Pallets')
                    ->formatStateUsing(fn ($state): string => number_format((int) $state).' fallback spots')
                    ->sortable(),
                TextColumn::make('max_product_weight_lbs')
                    ->label('Product Weight Limit')
                    ->formatStateUsing(fn ($state): string => filled($state) ? number_format((float) $state, 0).' lb' : 'Not set')
                    ->sortable(),
                IconColumn::make('piggyback_forklift_onboard')
                    ->label('Forklift Onboard')
                    ->boolean(),
                TextColumn::make('trips_count')
                    ->label('Trips')
                    ->counts('trips')
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
            ])
            ->filters([
                SelectFilter::make('configuration_type')
                    ->options(VehicleConfiguration::typeOptions()),
                TernaryFilter::make('piggyback_forklift_onboard')
                    ->label('Forklift onboard'),
                TernaryFilter::make('is_active')
                    ->label('Active'),
            ])
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
            'index' => ListVehicleConfigurations::route('/'),
            'create' => CreateVehicleConfiguration::route('/create'),
            'edit' => EditVehicleConfiguration::route('/{record}/edit'),
        ];
    }
}
