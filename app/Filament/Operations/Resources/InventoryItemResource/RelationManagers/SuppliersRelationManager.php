<?php

namespace App\Filament\Operations\Resources\InventoryItemResource\RelationManagers;

use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Actions\AttachAction;
use Filament\Actions\EditAction;
use Filament\Actions\DetachAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DetachBulkAction;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;

class SuppliersRelationManager extends RelationManager
{
    protected static string $relationship = 'suppliers';
    protected static ?string $title = 'Suppliers';
    protected static ?string $recordTitleAttribute = 'name';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('supplier_sku')
                    ->label('Supplier SKU')
                    ->maxLength(255),
                TextInput::make('minimum_order_quantity')
                    ->label('Minimum Order Quantity')
                    ->numeric()
                    ->default(1)
                    ->required(),
                TextInput::make('lead_time_days')
                    ->label('Lead Time (Days)')
                    ->numeric()
                    ->required(),
                Toggle::make('is_preferred')
                    ->dehydrateStateUsing(fn($state) => (bool) $state)
                    ->label('Preferred Supplier'),
                Textarea::make('notes')
                    ->rows(3),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('supplier_sku')
                    ->label('Supplier SKU')
                    ->searchable(),
                TextColumn::make('minimum_order_quantity')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('lead_time_days')
                    ->label('Lead Time')
                    ->suffix(' days'),
                IconColumn::make('is_preferred')
                    ->boolean()
                    ->label('Preferred'),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('is_preferred')
                    ->label('Preferred Suppliers Only'),
            ])
            ->headerActions([
                AttachAction::make()
                    ->preloadRecordSelect()
                    ->form(fn(AttachAction $action): array => [
                        $action->getRecordSelect(),
                        TextInput::make('supplier_sku')
                            ->label('Supplier SKU'),
                        TextInput::make('minimum_order_quantity')
                            ->label('Minimum Order Quantity')
                            ->numeric()
                            ->default(1),
                        TextInput::make('lead_time_days')
                            ->label('Lead Time (Days)')
                            ->numeric(),
                        Toggle::make('is_preferred')
                            ->label('Preferred Supplier'),
                    ])
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['is_preferred'] = $data['is_preferred'] ? 'true' : 'false';
                        return $data;
                    }),
            ])
            ->recordActions([
                EditAction::make(),
                DetachAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DetachBulkAction::make(),
                ]),
            ]);
    }
}
