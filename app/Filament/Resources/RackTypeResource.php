<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RackTypeResource\Pages\CreateRackType;
use App\Filament\Resources\RackTypeResource\Pages\EditRackType;
use App\Filament\Resources\RackTypeResource\Pages\ListRackTypes;
use App\Models\RackType;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class RackTypeResource extends Resource
{
    protected static ?string $model = RackType::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-squares-2x2';

    protected static string|\UnitEnum|null $navigationGroup = 'Delivery Management';

    protected static ?int $navigationSort = 41;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Rack Template')
                ->description('One rack occupies one trailer rack spot. Pallet capacity is defined per usable rack level.')
                ->schema([
                    TextInput::make('name')
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->maxLength(255),
                    TextInput::make('code')
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->maxLength(255),
                    TextInput::make('level_count')
                        ->label('Total Levels')
                        ->numeric()
                        ->integer()
                        ->minValue(1)
                        ->maxValue(10)
                        ->required(),
                    TextInput::make('pallet_capable_levels')
                        ->label('Pallet-capable Levels')
                        ->numeric()
                        ->integer()
                        ->minValue(0)
                        ->maxValue(10)
                        ->rules(['lte:level_count'])
                        ->required(),
                    TextInput::make('pallets_per_capable_level')
                        ->label('Pallets per Capable Level')
                        ->numeric()
                        ->integer()
                        ->minValue(1)
                        ->default(2)
                        ->required(),
                    Toggle::make('supports_standard_boxes')
                        ->label('Supports Standard Boxes')
                        ->default(true),
                    Toggle::make('supports_oversized_boxes')
                        ->label('Supports Oversized Boxes'),
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
                TextColumn::make('level_count')
                    ->label('Levels')
                    ->sortable(),
                TextColumn::make('pallet_capable_levels')
                    ->label('Pallet Levels')
                    ->sortable(),
                TextColumn::make('pallet_capacity')
                    ->label('Total Pallets')
                    ->state(fn (RackType $record): int => $record->palletCapacity()),
                IconColumn::make('supports_standard_boxes')
                    ->label('Standard')
                    ->boolean(),
                IconColumn::make('supports_oversized_boxes')
                    ->label('Oversized')
                    ->boolean(),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
            ])
            ->filters([
                TernaryFilter::make('supports_oversized_boxes')
                    ->label('Supports oversized'),
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
            'index' => ListRackTypes::route('/'),
            'create' => CreateRackType::route('/create'),
            'edit' => EditRackType::route('/{record}/edit'),
        ];
    }
}
