<?php

namespace App\Filament\Operations\Resources\SupplierResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class InventoryItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'inventoryItems';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('supplier_sku')
                    ->label('Supplier SKU'),
                Forms\Components\TextInput::make('cost_per_unit')
                    ->numeric()
                    ->required(),
                Forms\Components\TextInput::make('minimum_order_quantity')
                    ->numeric()
                    ->default(1),
                Forms\Components\TextInput::make('lead_time_days')
                    ->numeric(),
                Forms\Components\Toggle::make('is_preferred')
                    ->label('Preferred Supplier'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('name'),
                Tables\Columns\TextColumn::make('supplier_sku'),
                Tables\Columns\TextColumn::make('cost_per_unit')
                    ->money('USD'),
                Tables\Columns\IconColumn::make('is_preferred')
                    ->boolean(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\AttachAction::make(),
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