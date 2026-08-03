<?php

namespace App\Filament\Maintenance\Resources\MaintenanceWorkOrderResource\RelationManagers;

use App\Models\InventoryItem;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PartsRelationManager extends RelationManager
{
    protected static string $relationship = 'parts';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('inventory_item_id')->label('Inventory item')->options(InventoryItem::query()->orderBy('name')->pluck('name', 'id'))->searchable()->live()->afterStateUpdated(function ($state, Set $set): void {
                if ($state && ($item = InventoryItem::find($state))) {
                    $set('part_name', $item->name);
                }
            }),
            TextInput::make('part_name')->required(),
            TextInput::make('quantity')->numeric()->default(1)->minValue(0.01)->required(),
            TextInput::make('unit_cost')->numeric()->prefix('$')->minValue(0),
            Textarea::make('notes')->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('part_name')->label('Part')->searchable(),
            TextColumn::make('inventoryItem.sku')->label('SKU'),
            TextColumn::make('quantity')->numeric(),
            TextColumn::make('unit_cost')->money('USD'),
            TextColumn::make('total')->label('Total')->money('USD')->state(fn ($record) => (float) $record->quantity * (float) $record->unit_cost),
        ])->headerActions([CreateAction::make()])->recordActions([EditAction::make(), DeleteAction::make()]);
    }
}
