<?php

namespace App\Filament\Operations\Resources\SupplierResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;

class InventoryItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'inventoryItems';
    protected static ?string $title = 'Inventory Items';
    protected static ?string $recordTitleAttribute = 'name';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('supplier_sku')
                    ->label('Supplier SKU')
                    ->maxLength(255),
                Forms\Components\TextInput::make('cost_per_unit')
                    ->label('Cost per Unit')
                    ->numeric()
                    ->prefix('$')
                    ->required(),
                Forms\Components\TextInput::make('minimum_order_quantity')
                    ->label('Minimum Order Quantity')
                    ->numeric()
                    ->default(1),
                Forms\Components\TextInput::make('lead_time_days')
                    ->label('Lead Time (Days)')
                    ->numeric()
                    ->required(),
                Forms\Components\Toggle::make('is_preferred')
                    ->label('Preferred Supplier')
                    ->dehydrateStateUsing(fn($state) => DB::raw($state ? 'true' : 'false')),
                Forms\Components\Textarea::make('notes')
                    ->rows(3),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('sku')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('supplier_sku')
                    ->label('Supplier SKU')
                    ->searchable(),
                Tables\Columns\TextColumn::make('cost_per_unit')
                    ->money('USD')
                    ->sortable(),
                Tables\Columns\TextColumn::make('minimum_order_quantity')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('lead_time_days')
                    ->label('Lead Time')
                    ->suffix(' days'),
                Tables\Columns\IconColumn::make('is_preferred')
                    ->boolean()
                    ->label('Preferred'),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_preferred')
                    ->label('Preferred Items Only'),
            ])
            ->headerActions([
                Tables\Actions\AttachAction::make()
                    ->preloadRecordSelect()
                    ->form(fn(Tables\Actions\AttachAction $action): array => [
                        $action->getRecordSelect()
                            ->searchable()
                            ->preload(),
                        Forms\Components\TextInput::make('supplier_sku')
                            ->label('Supplier SKU'),
                        Forms\Components\TextInput::make('cost_per_unit')
                            ->label('Cost per Unit')
                            ->numeric()
                            ->prefix('$')
                            ->required()
                            ->default(0.00),
                        Forms\Components\TextInput::make('minimum_order_quantity')
                            ->label('Minimum Order Quantity')
                            ->numeric()
                            ->default(1),
                        Forms\Components\TextInput::make('lead_time_days')
                            ->label('Lead Time (Days)')
                            ->numeric(),
                        Forms\Components\Toggle::make('is_preferred')
                            ->label('Preferred Supplier')
                            ->default(false)
                    ])
                    ->beforeAttaching(function (array $data) {})
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['cost_per_unit'] = number_format((float)$data['cost_per_unit'], 2, '.', '');
                        $data['is_preferred'] = $data['is_preferred'] === true ? 'true' : 'false';
                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DetachAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DetachBulkAction::make(),
                ]),
            ]);
    }
}
