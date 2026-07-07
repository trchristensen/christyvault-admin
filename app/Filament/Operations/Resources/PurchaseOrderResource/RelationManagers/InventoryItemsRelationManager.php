<?php

namespace App\Filament\Operations\Resources\PurchaseOrderResource\RelationManagers;

use Filament\Tables\Columns\TextColumn;
use Filament\Actions\AttachAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Exception;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Actions\DetachAction;
use Filament\Actions\DetachBulkAction;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use App\Models\InventoryItem;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Filament\Forms\Get;
use Illuminate\Support\Collection;

class InventoryItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';
    protected static ?string $recordTitleAttribute = 'name';
    protected static ?string $title = 'Purchase Order Items';

    public function mount(): void
    {
        parent::mount();
        Log::info('RelationManager Mounted', [
            'owner_record' => $this->getOwnerRecord()->toArray(),
            'relationship' => static::$relationship
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Item Name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('sku')
                    ->label('SKU')
                    ->searchable(),

                TextColumn::make('pivot.supplier_sku')
                    ->label('Supplier SKU')
                    ->searchable(),

                TextColumn::make('pivot.quantity')
                    ->label('Quantity')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('pivot.unit_price')
                    ->label('Unit Price')
                    ->money('USD')
                    ->sortable(),

                TextColumn::make('pivot.total_price')
                    ->label('Total')
                    ->money('USD')
                    ->sortable(),

                TextColumn::make('pivot.received_quantity')
                    ->label('Received')
                    ->numeric()
                    ->sortable(),
            ])
            ->headerActions([
                AttachAction::make()
                    ->preloadRecordSelect()
                    ->beforeFormFilled(function (array $data) {
                        Log::info('Before Form Filled', ['data' => $data]);
                    })
                    ->afterFormFilled(function (array $data) {
                        Log::info('After Form Filled', ['data' => $data]);
                    })
                    ->beforeFormValidated(function (array $data) {
                        Log::info('Before Form Validated', ['data' => $data]);
                    })
                    ->afterFormValidated(function (array $data) {
                        Log::info('After Form Validated', ['data' => $data]);
                    })
                    ->before(function () {
                        Log::info('Before Attachment');
                        // Log the current state of the relationship
                        Log::info('Current Items', [
                            'items' => DB::table('purchase_order_items')
                                ->where('purchase_order_id', $this->getOwnerRecord()->id)
                                ->get()
                                ->toArray()
                        ]);
                    })
                    ->after(function () {
                        Log::info('After Attachment');
                        // Log the new state of the relationship
                        Log::info('Updated Items', [
                            'items' => DB::table('purchase_order_items')
                                ->where('purchase_order_id', $this->getOwnerRecord()->id)
                                ->get()
                                ->toArray()
                        ]);
                    })
                    ->form(fn(AttachAction $action): array => [
                        Select::make('recordId')
                            ->label('Inventory Item')
                            ->options(fn(): Collection => InventoryItem::query()
                                ->with(['suppliers' => function($query) {
                                    $query->select('suppliers.id', 'suppliers.name')
                                        ->wherePivot('is_preferred', '=', DB::raw('true'))
                                        ->withPivot('supplier_sku');
                                }])
                                ->leftJoin('inventory_item_suppliers', function($join) {
                                    $join->on('inventory_items.id', '=', 'inventory_item_suppliers.inventory_item_id')
                                        ->where('inventory_item_suppliers.supplier_id', '=', $this->getOwnerRecord()->supplier_id)
                                        ->where('inventory_item_suppliers.is_preferred', '=', DB::raw('true'));
                                })
                                ->whereNotExists(function ($query) {
                                    $query->select(DB::raw(1))
                                        ->from('purchase_order_items')
                                        ->whereColumn('purchase_order_items.inventory_item_id', 'inventory_items.id')
                                        ->where('purchase_order_items.purchase_order_id', $this->getOwnerRecord()->id);
                                })
                                ->select('inventory_items.*')
                                ->orderByRaw('CASE WHEN inventory_item_suppliers.supplier_id IS NOT NULL THEN 0 ELSE 1 END')
                                ->get()
                                ->mapWithKeys(function ($item) {
                                    $preferredSupplier = $item->suppliers->first();
                                    $supplierInfo = $preferredSupplier 
                                        ? $preferredSupplier->name . 
                                          ($preferredSupplier->pivot->supplier_sku ? ' (' . $preferredSupplier->pivot->supplier_sku . ')' : '')
                                        : '';

                                    return [
                                        $item->id => '<div style="line-height: 1.2;">
                                                        <span style="font-size: 0.9em; font-weight: bold;">' . $item->sku . '</span><br>' . 
                                                        $item->name . 
                                                        ($supplierInfo ? '<br>' . $supplierInfo : '') .
                                                    '</div>'
                                    ];
                                }))
                            ->required()
                            ->searchable()
                            ->preload()
                            ->allowHtml(),
                        TextInput::make('supplier_sku')
                            ->label('Supplier SKU')
                            ->default(function () {
                                $firstItem = $this->getOwnerRecord()->items->first();
                                return $firstItem?->sku ?? null;
                            })
                            ->nullable(),
                        TextInput::make('quantity')
                            ->label(function () {
                                $firstItem = $this->getOwnerRecord()->items->first();
                                return 'Quantity' . ($firstItem ? ' (' . $firstItem->unit_of_measure . ')' : '');
                            })
                            ->numeric()
                            ->required()
                            ->default(1)
                            ->minValue(0),
                        TextInput::make('unit_price')
                            ->numeric()
                            ->prefix('$')
                            ->required()
                            ->default(0)
                            ->minValue(0),
                        TextInput::make('received_quantity')
                            ->numeric()
                            ->default(0)
                            ->minValue(0),
                    ])
                    ->using(function (array $data, Model $record): array {
                        Log::info('Using Method Called', [
                            'data' => $data,
                            'record' => $record->toArray(),
                            'owner_record' => $this->getOwnerRecord()->toArray()
                        ]);

                        try {
                            DB::beginTransaction();

                            $totalPrice = floatval($data['quantity']) * floatval($data['unit_price']);

                            $processedData = [
                                'purchase_order_id' => $this->getOwnerRecord()->id,
                                'inventory_item_id' => $record->id,
                                'supplier_id' => $this->getOwnerRecord()->supplier_id,
                                'supplier_sku' => $data['supplier_sku'] ?? null,
                                'quantity' => $data['quantity'],
                                'unit_price' => $data['unit_price'],
                                'total_price' => $totalPrice,
                                'received_quantity' => $data['received_quantity'] ?? 0,
                            ];

                            // Log the data we're about to insert
                            Log::info('Attempting to insert:', $processedData);

                            // Insert directly into the pivot table
                            DB::table('purchase_order_items')->insert($processedData);

                            DB::commit();

                            Log::info('Successfully inserted purchase order item');

                            // Return the pivot data for the relationship
                            return [
                                'supplier_sku' => $data['supplier_sku'] ?? null,
                                'quantity' => $data['quantity'],
                                'unit_price' => $data['unit_price'],
                                'total_price' => $totalPrice,
                                'received_quantity' => $data['received_quantity'] ?? 0,
                            ];
                        } catch (Exception $e) {
                            DB::rollBack();
                            Log::error('Failed to attach item:', [
                                'error' => $e->getMessage(),
                                'trace' => $e->getTraceAsString()
                            ]);
                            throw $e;
                        }
                    }),

                CreateAction::make()
                    ->label('New inventory item')
                    ->model(InventoryItem::class)
                    ->schema([
                        TextInput::make('name')
                            ->required(),
                        TextInput::make('sku')
                            ->required()
                            ->unique(ignoreRecord: true),
                        TextInput::make('description')
                            ->nullable(),
                        TextInput::make('minimum_stock')
                            ->numeric()
                            ->default(0),
                        TextInput::make('current_stock')
                            ->numeric()
                            ->default(0),
                    ]),
            ])
            ->recordActions([
                EditAction::make()
                    ->schema(fn(EditAction $action): array => [
                        TextInput::make('supplier_sku')
                            ->label('Supplier SKU'),
                        TextInput::make('quantity')
                            ->required()
                            ->numeric(),
                        TextInput::make('unit_price')
                            ->required()
                            ->numeric()
                            ->prefix('$'),
                        TextInput::make('received_quantity')
                            ->numeric()
                            ->visible(fn($livewire) =>
                            $livewire->ownerRecord->status === 'received'),
                    ]),
                DetachAction::make(),
            ])
            ->toolbarActions([
                DetachBulkAction::make(),
            ]);
    }
}
