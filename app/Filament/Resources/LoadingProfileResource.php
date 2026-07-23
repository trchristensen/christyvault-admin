<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LoadingProfileResource\Pages\CreateLoadingProfile;
use App\Filament\Resources\LoadingProfileResource\Pages\EditLoadingProfile;
use App\Filament\Resources\LoadingProfileResource\Pages\ListLoadingProfiles;
use App\Filament\Resources\LoadingProfileResource\Pages\ViewLoadingProfile;
use App\Filament\Resources\LoadingProfileResource\RelationManagers\ProductsRelationManager;
use App\Models\LoadingProfile;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class LoadingProfileResource extends Resource
{
    protected static ?string $model = LoadingProfile::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cube-transparent';

    protected static string|\UnitEnum|null $navigationGroup = 'Delivery Management';

    protected static ?int $navigationSort = 40;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Product Loading Profile')
                ->description('Reusable loading rules that can be assigned to one or many products.')
                ->schema([
                    TextInput::make('name')
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->maxLength(255),
                    TextInput::make('code')
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->helperText('Stable internal identifier, such as standard_rack_box.')
                        ->maxLength(255),
                    Select::make('handling_method')
                        ->options(LoadingProfile::handlingMethodOptions())
                        ->default(LoadingProfile::HANDLING_INDIVIDUAL)
                        ->required()
                        ->live()
                        ->native(false),
                    TextInput::make('units_per_pallet')
                        ->label('Products per Pallet')
                        ->numeric()
                        ->integer()
                        ->minValue(1)
                        ->required(fn (Get $get): bool => $get('handling_method') === LoadingProfile::HANDLING_PALLET)
                        ->visible(fn (Get $get): bool => $get('handling_method') === LoadingProfile::HANDLING_PALLET),
                    TextInput::make('units_per_rack_position')
                        ->label('Products per Rack Position')
                        ->helperText('Number of products that can share one level/position in a rack. Usually 1; G5 covers use 4.')
                        ->numeric()
                        ->integer()
                        ->default(1)
                        ->minValue(1)
                        ->required()
                        ->visible(fn (Get $get): bool => $get('handling_method') === LoadingProfile::HANDLING_INDIVIDUAL),
                    TextInput::make('flatbed_fallback_units_per_spot')
                        ->label('Direct Flatbed Products per Spot')
                        ->helperText('Optional overflow after compatible rack bays are full. Leave blank when this product may not load directly on the flatbed.')
                        ->numeric()
                        ->integer()
                        ->minValue(1)
                        ->visible(fn (Get $get): bool => $get('handling_method') === LoadingProfile::HANDLING_INDIVIDUAL),
                    TextInput::make('full_load_units')
                        ->label('Physical Full-load Quantity')
                        ->helperText('Maximum when this is the only product profile on the truck. The vehicle weight limit always takes priority.')
                        ->numeric()
                        ->integer()
                        ->minValue(1),
                    TextInput::make('pallet_compatibility_group')
                        ->label('Mixed-pallet Group')
                        ->helperText('Only profiles with the same nonblank group may share a pallet. Example: boxed_urn_products.')
                        ->maxLength(255)
                        ->visible(fn (Get $get): bool => $get('handling_method') === LoadingProfile::HANDLING_PALLET),
                    Select::make('rack_requirement')
                        ->options(LoadingProfile::rackRequirementOptions())
                        ->default(LoadingProfile::RACK_STANDARD)
                        ->required()
                        ->native(false),
                    Select::make('required_rack_level')
                        ->label('Required Rack Level')
                        ->options(LoadingProfile::requiredRackLevelOptions())
                        ->default(LoadingProfile::LEVEL_ANY)
                        ->required()
                        ->native(false),
                    Select::make('required_rack_type_id')
                        ->label('Preferred Rack Type')
                        ->relationship('requiredRackType', 'name')
                        ->searchable()
                        ->preload()
                        ->helperText('Used when the planner must open a new rack spot.')
                        ->placeholder('No preferred rack'),
                    Select::make('allowedRackTypes')
                        ->label('Allowed Rack Types')
                        ->relationship('allowedRackTypes', 'name')
                        ->multiple()
                        ->searchable()
                        ->preload()
                        ->helperText('Existing open positions in these rack types may be reused before opening a new rack.'),
                    Select::make('placement_strategy')
                        ->label('Rack Placement Strategy')
                        ->options(LoadingProfile::placementStrategyOptions())
                        ->default(LoadingProfile::PLACEMENT_ONE_PER_LEVEL)
                        ->required()
                        ->native(false),
                    Toggle::make('is_stackable')
                        ->label('Product Can Be Stacked')
                        ->default(true),
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
                TextColumn::make('handling_method')
                    ->label('Handling')
                    ->formatStateUsing(fn (string $state): string => LoadingProfile::handlingMethodOptions()[$state] ?? $state)
                    ->badge()
                    ->sortable(),
                TextColumn::make('units_per_pallet')
                    ->label('Per Pallet')
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('units_per_rack_position')
                    ->label('Per Rack Position')
                    ->placeholder('1')
                    ->sortable(),
                TextColumn::make('flatbed_fallback_units_per_spot')
                    ->label('Direct Flatbed / Spot')
                    ->placeholder('Not allowed')
                    ->sortable(),
                TextColumn::make('full_load_units')
                    ->label('Full Load')
                    ->placeholder('Placement based')
                    ->sortable(),
                TextColumn::make('pallet_compatibility_group')
                    ->label('Mixed-pallet Group')
                    ->placeholder('Same SKU only')
                    ->searchable(),
                TextColumn::make('rack_requirement')
                    ->label('Rack')
                    ->formatStateUsing(fn (string $state): string => LoadingProfile::rackRequirementOptions()[$state] ?? $state)
                    ->wrap(),
                TextColumn::make('placement_strategy')
                    ->label('Placement')
                    ->formatStateUsing(fn (string $state): string => LoadingProfile::placementStrategyOptions()[$state] ?? $state)
                    ->wrap(),
                TextColumn::make('products_count')
                    ->label('Products')
                    ->counts('products')
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
            ])
            ->filters([
                SelectFilter::make('handling_method')
                    ->options(LoadingProfile::handlingMethodOptions()),
                TernaryFilter::make('is_active')
                    ->label('Active'),
            ])
            ->recordActions([
                ViewAction::make(),
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
            'index' => ListLoadingProfiles::route('/'),
            'create' => CreateLoadingProfile::route('/create'),
            'view' => ViewLoadingProfile::route('/{record}'),
            'edit' => EditLoadingProfile::route('/{record}/edit'),
        ];
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Loading Rules')
                ->schema([
                    TextEntry::make('name'),
                    TextEntry::make('code')
                        ->label('Internal Code')
                        ->copyable(),
                    TextEntry::make('handling_method')
                        ->label('Handling')
                        ->formatStateUsing(fn (string $state): string => LoadingProfile::handlingMethodOptions()[$state] ?? $state)
                        ->badge(),
                    TextEntry::make('units_per_pallet')
                        ->label('Products per Pallet')
                        ->placeholder('Not palletized'),
                    TextEntry::make('units_per_rack_position')
                        ->label('Products per Rack Position')
                        ->placeholder('1'),
                    TextEntry::make('flatbed_fallback_units_per_spot')
                        ->label('Direct Flatbed Products per Spot')
                        ->placeholder('Not allowed'),
                    TextEntry::make('full_load_units')
                        ->label('Physical Full-load Quantity')
                        ->placeholder('Placement based'),
                    TextEntry::make('pallet_compatibility_group')
                        ->label('Mixed-pallet Group')
                        ->placeholder('Same SKU only'),
                    TextEntry::make('rack_requirement')
                        ->label('Rack Requirement')
                        ->formatStateUsing(fn (string $state): string => LoadingProfile::rackRequirementOptions()[$state] ?? $state),
                    TextEntry::make('required_rack_level')
                        ->label('Required Rack Level')
                        ->formatStateUsing(fn (string $state): string => LoadingProfile::requiredRackLevelOptions()[$state] ?? $state),
                    TextEntry::make('requiredRackType.name')
                        ->label('Preferred Rack Type')
                        ->placeholder('No preferred rack'),
                    TextEntry::make('allowedRackTypes.name')
                        ->label('Allowed Rack Types')
                        ->badge()
                        ->placeholder('No compatible rack types configured'),
                    TextEntry::make('placement_strategy')
                        ->label('Rack Placement Strategy')
                        ->formatStateUsing(fn (string $state): string => LoadingProfile::placementStrategyOptions()[$state] ?? $state),
                    IconEntry::make('is_stackable')
                        ->label('Stackable')
                        ->boolean(),
                    IconEntry::make('is_active')
                        ->label('Active')
                        ->boolean(),
                    TextEntry::make('notes')
                        ->columnSpanFull()
                        ->placeholder('No notes'),
                ])
                ->columns(2),
        ]);
    }

    public static function getRelations(): array
    {
        return [
            ProductsRelationManager::class,
        ];
    }
}
