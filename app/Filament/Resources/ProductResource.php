<?php

namespace App\Filament\Resources;

use App\Enums\UnitOfMeasure;
use App\Filament\Resources\ProductResource\Pages\CreateProduct;
use App\Filament\Resources\ProductResource\Pages\EditProduct;
use App\Filament\Resources\ProductResource\Pages\ListProducts;
use App\Models\LoadingProfile;
use App\Models\Product;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static string|\UnitEnum|null $navigationGroup = 'Directories';

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
                TextInput::make('weight_lbs')
                    ->label('Shipping Weight')
                    ->helperText('Weight of one order unit, whether it is a complete product or an individual component.')
                    ->numeric()
                    ->minValue(0)
                    ->suffix('lb'),
                Select::make('loading_profile_id')
                    ->label('Loading Profile')
                    ->relationship('loadingProfile', 'name')
                    ->searchable()
                    ->preload()
                    ->helperText('Defines pallet, stacking, and rack requirements for the load planner.'),
                FileUpload::make('featured_image')
                    ->image()
                    ->imageEditor()
                    ->columnSpanFull(),
                Checkbox::make('is_active')
                    ->default('TRUE')
                    ->label('Active')
                    ->dehydrateStateUsing(fn ($state) => $state ? 'TRUE' : 'FALSE'),
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
                    ->formatStateUsing(fn ($state) => $state?->label() ?? 'N/A')
                    ->sortable(),
                TextColumn::make('product_type')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('price')
                    ->money()
                    ->sortable(),
                TextColumn::make('stock')
                    ->sortable(),
                TextColumn::make('weight_lbs')
                    ->label('Weight')
                    ->formatStateUsing(fn ($state): string => filled($state) ? number_format((float) $state, 2).' lb' : '—')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('loadingProfile.name')
                    ->label('Loading Profile')
                    ->placeholder('Missing rules')
                    ->badge()
                    ->searchable()
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
                TernaryFilter::make('loading_profile_id')
                    ->label('Loading Profile')
                    ->nullable()
                    ->trueLabel('Assigned')
                    ->falseLabel('Missing')
                    ->native(false),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('assignLoadingProfile')
                        ->label('Assign Loading Profile')
                        ->icon('heroicon-o-cube-transparent')
                        ->form([
                            Select::make('loading_profile_id')
                                ->label('Loading Profile')
                                ->options(fn () => LoadingProfile::query()
                                    ->where('is_active', true)
                                    ->orderBy('name')
                                    ->pluck('name', 'id'))
                                ->searchable()
                                ->required(),
                        ])
                        ->action(function (Collection $records, array $data): void {
                            $records->each->update([
                                'loading_profile_id' => $data['loading_profile_id'],
                            ]);
                        })
                        ->deselectRecordsAfterCompletion(),
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
