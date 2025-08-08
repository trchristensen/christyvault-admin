<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductResource\Pages;
use App\Models\Product;
use App\Enums\UnitOfMeasure;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationGroup = 'Directories';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('sku')
                    ->required()
                    ->label('Product Number')
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Select::make('unit')
                    ->label('Unit of Measure')
                    ->options(UnitOfMeasure::getOptions())
                    ->searchable()
                    ->preload(),
                Forms\Components\Select::make('product_type')
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
                Forms\Components\Textarea::make('description')
                    ->maxLength(65535)
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('price')
                    ->required()
                    ->default(0)
                    ->numeric()
                    ->prefix('$'),
                Forms\Components\TextInput::make('stock')
                    ->required()
                    ->numeric()
                    ->default(0),
                Forms\Components\FileUpload::make('featured_image')
                    ->image()
                    ->imageEditor()
                    ->columnSpanFull(),
                Forms\Components\Checkbox::make('is_active')
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
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('sku')
                    ->searchable(),
                Tables\Columns\TextColumn::make('unit')
                    ->label('Unit')
                    ->formatStateUsing(fn($state) => $state?->label() ?? 'N/A')
                    ->sortable(),
                Tables\Columns\TextColumn::make('product_type')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('price')
                    ->money()
                    ->sortable(),
                Tables\Columns\TextColumn::make('stock')
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean(),
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
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
        ];
    }
}
