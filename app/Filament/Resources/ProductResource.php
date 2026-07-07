<?php

namespace App\Filament\Resources;

use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Checkbox;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Actions\EditAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Resources\ProductResource\Pages\ListProducts;
use App\Filament\Resources\ProductResource\Pages\CreateProduct;
use App\Filament\Resources\ProductResource\Pages\EditProduct;
use App\Filament\Resources\ProductResource\Pages;
use App\Models\Product;
use App\Enums\UnitOfMeasure;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static string | \UnitEnum | null $navigationGroup = 'Directories';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('sku')
                    ->required()
                    ->label('Product Number')
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Select::make('unit')
                    ->label('Unit of Measure')
                    ->options(UnitOfMeasure::getOptions())
                    ->searchable()
                    ->preload(),
                Select::make('product_type')
                    ->label('Product Type')
                    ->options([
                        'Wilbert Burial Vaults' => 'Wilbert Burial Vaults',
                        'Wilbert Urn Vaults' => 'Wilbert Urn Vaults',
                        'Wilbert Cremation Urns' => 'Wilbert Cremation Urns',
                        'Outer Burial Containers' => 'Outer Burial Containers',
                        'Marker Foundations' => 'Marker Foundations',
                        'Cremation Urn' => 'Cremation Urn',
                        'Other' => 'Other',
                    ])
                    ->native(false),
                Textarea::make('description')
                    ->maxLength(65535)
                    ->columnSpanFull(),
                TextInput::make('price')
                    ->required()
                    ->default(0)
                    ->numeric()
                    ->prefix('$'),
                TextInput::make('stock')
                    ->required()
                    ->numeric()
                    ->default(0),
                FileUpload::make('featured_image')
                    ->image()
                    ->imageEditor()
                    ->columnSpanFull(),
                Checkbox::make('is_active')
                    ->default('TRUE')
                    ->label('Active')
                    ->dehydrateStateUsing(fn($state) => $state ? 'TRUE' : 'FALSE'),
                // Forms\Components\KeyValue::make('specifications')
                //     ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('sku')
                    ->searchable(),
                TextColumn::make('unit')
                    ->label('Unit')
                    ->formatStateUsing(fn($state) => $state?->label() ?? 'N/A')
                    ->sortable(),
                TextColumn::make('product_type')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('price')
                    ->money()
                    ->sortable(),
                TextColumn::make('stock')
                    ->sortable(),
                IconColumn::make('is_active')
                    ->boolean(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
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

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProducts::route('/'),
            'create' => CreateProduct::route('/create'),
            'edit' => EditProduct::route('/{record}/edit'),
        ];
    }
}
