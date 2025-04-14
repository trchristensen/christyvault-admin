<?php

namespace App\Filament\Operations\Resources;

use App\Filament\Operations\Resources\PurchaseOrderResource\Pages;
use App\Filament\Operations\Resources\PurchaseOrderResource\RelationManagers;
use App\Models\PurchaseOrder;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms\Get;
use Illuminate\Support\Facades\Auth;
use App\Models\InventoryItem;
use App\Models\Supplier;
use Filament\Forms\Components\Select;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use App\Models\PurchaseOrderDocument;
use Illuminate\Support\Facades\DB;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Collection;

class PurchaseOrderResource extends Resource
{
    protected static ?string $model = PurchaseOrder::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('supplier.name')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\BadgeColumn::make('status')
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'draft' => 'Draft',
                        'submitted' => 'Submitted',
                        'received' => 'Received',
                        'awaiting_invoice' => 'Awaiting Invoice',
                        'cancelled' => 'Cancelled',
                        'completed' => 'Completed',
                        default => $state,
                    })
                    ->colors([
                        'warning' => 'draft',
                        'primary' => 'submitted',
                        'success' => 'received',
                        'danger' => 'cancelled',
                        'info' => 'awaiting_invoice',
                        'success' => 'completed',
                    ]),

                Tables\Columns\TextColumn::make('order_date')
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('expected_delivery_date')
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('received_date')
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('total_amount')
                    ->money('USD')
                    ->sortable(),

                Tables\Columns\TextColumn::make('items.name')
                    ->label('Items')
                    ->listWithLineBreaks()
                    ->limitList(3)
                    ->expandableLimitedList()
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\IconColumn::make('is_liner_load')
                    ->label('Liner Load')
                    ->boolean()
                    ->visible(fn (?Model $record): bool => 
                        $record?->supplier?->name === 'Wilbert'
                    ),

              

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->groups([
                Tables\Grouping\Group::make('status')
                    ->label('Status')
                    ->getTitleFromRecordUsing(fn ($record): string => match ($record->status) {
                        'draft' => 'Draft',
                        'submitted' => 'Submitted',
                        'received' => 'Received',
                        'awaiting_invoice' => 'Awaiting Invoice',
                        'cancelled' => 'Cancelled',
                        'completed' => 'Completed',
                        default => ucfirst($record->status),
                    })
                    ->collapsible()
            ])
            ->defaultGroup('status')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->multiple()
                    ->options([
                        'draft' => 'Draft',
                        'submitted' => 'Submitted',
                        'received' => 'Received',
                        'awaiting_invoice' => 'Awaiting Invoice',
                        'cancelled' => 'Cancelled',
                        'completed' => 'Completed',
                    ])
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('merge')
                    ->icon('heroicon-o-arrow-path-rounded-square')
                    ->label('Merge')
                    ->form([
                        Forms\Components\Select::make('merge_with')
                            ->label('Merge With')
                            ->options(function (Model $record) {
                                // Get other draft POs from same supplier
                                return PurchaseOrder::where('supplier_id', $record->supplier_id)
                                    ->where('status', 'draft')
                                    ->where('id', '!=', $record->id)
                                    ->with(['supplier', 'items']) // Eager load relationships
                                    ->get()
                                    ->mapWithKeys(function ($po) {
                                        // Format items list (limit to first 3)
                                        $items = $po->items->take(3)->map(function ($item) {
                                            return "{$item->pivot->quantity}x {$item->name}";
                                        })->join(', ');
                                        
                                        // Add ellipsis if there are more items
                                        if ($po->items->count() > 3) {
                                            $items .= ", ... +" . ($po->items->count() - 3) . " more";
                                        }

                                        // Format the option label
                                        $label = "PO #{$po->id} - {$po->supplier->name}\n" .
                                            "Created: " . $po->created_at->format('M j, Y') . "\n" .
                                            "Items: " . ($items ?: 'No items');

                                        return [$po->id => $label];
                                    });
                            })
                            ->searchable()
                            ->required()
                            ->columnSpanFull()
                            ->helperText('Search by PO number, supplier name, or items')
                    ])
                    ->action(function (Model $record, array $data): void {
                        $targetPo = PurchaseOrder::find($data['merge_with']);
                        
                        // Start transaction
                        DB::transaction(function () use ($record, $targetPo) {
                            // Merge notes if both exist
                            if ($record->notes && $targetPo->notes) {
                                $targetPo->notes = $targetPo->notes . "\n\n--- Merged from PO #{$record->id} ---\n" . $record->notes;
                            } elseif ($record->notes) {
                                $targetPo->notes = $record->notes;
                            }

                            // For each item in the source PO
                            foreach ($record->items as $item) {
                                // Check if item exists in target PO
                                $existingItem = $targetPo->items()
                                    ->where('inventory_item_id', $item->id)
                                    ->first();

                                if ($existingItem) {
                                    // Update quantity if exists
                                    $newQuantity = $existingItem->pivot->quantity + $item->pivot->quantity;
                                    $targetPo->items()->updateExistingPivot($item->id, [
                                        'quantity' => $newQuantity,
                                        'total_price' => $newQuantity * $item->pivot->unit_price,
                                    ]);
                                } else {
                                    // Add new item if doesn't exist
                                    $targetPo->items()->attach($item->id, [
                                        'quantity' => $item->pivot->quantity,
                                        'unit_price' => $item->pivot->unit_price,
                                        'total_price' => $item->pivot->total_price,
                                        'supplier_sku' => $item->pivot->supplier_sku,
                                        'received_quantity' => $item->pivot->received_quantity,
                                    ]);
                                }
                            }

                            // Move all documents from source to target
                            foreach ($record->documents as $document) {
                                $document->update([
                                    'purchase_order_id' => $targetPo->id,
                                    'notes' => $document->notes ? 
                                        $document->notes . "\n(Moved from PO #{$record->id})" : 
                                        "(Moved from PO #{$record->id})"
                                ]);
                            }

                            // Update liner load status if either PO is a liner load
                            if ($record->is_liner_load || $targetPo->is_liner_load) {
                                $targetPo->update(['is_liner_load' => true]);
                            }

                            // Save any changes to the target PO
                            $targetPo->save();

                            // Delete the source PO
                            $record->delete();
                        });

                        Notification::make()
                            ->title('Purchase Orders Merged')
                            ->success()
                            ->send();
                    })
                    ->visible(fn (Model $record) => 
                        $record->status === 'draft' && 
                        PurchaseOrder::where('supplier_id', $record->supplier_id)
                            ->where('status', 'draft')
                            ->where('id', '!=', $record->id)
                            ->exists()
                    )
                    ->requiresConfirmation()
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\BulkAction::make('mergeBulk')
                        ->label('Merge Selected')
                        ->icon('heroicon-o-arrow-path-rounded-square')
                        ->action(function (Collection $records): void {
                            // Group records by supplier
                            $groupedBySupplier = $records->groupBy('supplier_id');
                            
                            foreach ($groupedBySupplier as $supplierId => $pos) {
                                // Skip if only one PO for this supplier
                                if ($pos->count() <= 1) continue;

                                // Use the first PO as the target
                                $targetPo = $pos->shift();

                                // Merge all other POs into the target
                                foreach ($pos as $sourcePo) {
                                    DB::transaction(function () use ($sourcePo, $targetPo) {
                                        // Merge notes if both exist
                                        if ($sourcePo->notes && $targetPo->notes) {
                                            $targetPo->notes = $targetPo->notes . "\n\n--- Merged from PO #{$sourcePo->id} ---\n" . $sourcePo->notes;
                                        } elseif ($sourcePo->notes) {
                                            $targetPo->notes = $sourcePo->notes;
                                        }

                                        foreach ($sourcePo->items as $item) {
                                            $existingItem = $targetPo->items()
                                                ->where('inventory_item_id', $item->id)
                                                ->first();

                                            if ($existingItem) {
                                                $newQuantity = $existingItem->pivot->quantity + $item->pivot->quantity;
                                                $targetPo->items()->updateExistingPivot($item->id, [
                                                    'quantity' => $newQuantity,
                                                    'total_price' => $newQuantity * $item->pivot->unit_price,
                                                ]);
                                            } else {
                                                $targetPo->items()->attach($item->id, [
                                                    'quantity' => $item->pivot->quantity,
                                                    'unit_price' => $item->pivot->unit_price,
                                                    'total_price' => $item->pivot->total_price,
                                                    'supplier_sku' => $item->pivot->supplier_sku,
                                                    'received_quantity' => $item->pivot->received_quantity,
                                                ]);
                                            }
                                        }

                                        // Move all documents from source to target
                                        foreach ($sourcePo->documents as $document) {
                                            $document->update([
                                                'purchase_order_id' => $targetPo->id,
                                                'notes' => $document->notes ? 
                                                    $document->notes . "\n(Moved from PO #{$sourcePo->id})" : 
                                                    "(Moved from PO #{$sourcePo->id})"
                                            ]);
                                        }

                                        // Update liner load status if either PO is a liner load
                                        if ($sourcePo->is_liner_load || $targetPo->is_liner_load) {
                                            $targetPo->update(['is_liner_load' => true]);
                                        }

                                        // Save any changes to the target PO
                                        $targetPo->save();

                                        $sourcePo->delete();
                                    });
                                }
                            }

                            Notification::make()
                                ->title('Purchase Orders Merged')
                                ->body('Selected purchase orders have been merged by supplier')
                                ->success()
                                ->send();
                        })
                        ->visible(function (?Collection $records): bool {
                            if (!$records || $records->isEmpty()) {
                                return false;
                            }
                            
                            // Check if any non-draft orders are selected
                            if ($records->where('status', '!=', 'draft')->isNotEmpty()) {
                                return false;
                            }

                            // Check if we have multiple POs for at least one supplier
                            $hasMultiplePosForSupplier = $records->groupBy('supplier_id')
                                ->some(fn ($group) => $group->count() > 1);

                            return $hasMultiplePosForSupplier;
                        })
                        ->requiresConfirmation()
                        ->modalDescription('This will merge all selected purchase orders for each supplier into one. Orders will be grouped by supplier.')
                ]),
            ]);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('supplier_id')
                    ->relationship('supplier', 'name')
                    ->required()
                    ->searchable()
                    ->preload()
                    ->live(),

                Forms\Components\Toggle::make('is_liner_load')
                    ->live()
                    ->label('Liner Load')
                    ->dehydrateStateUsing(fn($state): string => $state ? 'true' : 'false')
                    ->default(false)
                    ->visible(fn (Get $get): bool => 
                        Supplier::find($get('supplier_id'))?->name === 'Wilbert'
                    )
                    ->default(false),

                Forms\Components\Select::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'submitted' => 'Submitted',
                        'received' => 'Received',
                        'awaiting_invoice' => 'Awaiting Invoice',
                        'cancelled' => 'Cancelled',
                        'completed' => 'Completed',
                    ])
                    ->default('draft')
                    ->required(),

                Forms\Components\DatePicker::make('order_date')
                    ->label(fn (Get $get) => $get('is_liner_load') ? 'Order Deadline' : 'Order Date')
                    ->live()
                    ->default(now())
                    ->required(),

                Forms\Components\DatePicker::make('expected_delivery_date'),

                Forms\Components\DatePicker::make('received_date'),

                Forms\Components\TextInput::make('total_amount')
                    ->required()
                    ->numeric()
                    ->prefix('$')
                    ->minValue(0)
                    ->default(0),

                Forms\Components\Textarea::make('notes')
                    ->columnSpanFull(),

                Forms\Components\Hidden::make('created_by_user_id')
                    ->default(fn() => Auth::id())
                    ->dehydrated(fn($state) => filled($state)),

                Forms\Components\Section::make('Documents')
                    ->schema([
                        Forms\Components\Repeater::make('documents')
                            ->relationship()
                            ->schema([
                                Forms\Components\Select::make('type')
                                    ->options(PurchaseOrderDocument::getTypes())
                                    ->required(),
                                Forms\Components\TextInput::make('document_number')
                                    ->label('Document Number')
                                    ->helperText('e.g. Invoice number, BOL number, Quote number')
                                    ->maxLength(255),
                                Forms\Components\FileUpload::make('file_path')
                                    ->label('Document File')
                                    ->disk('r2')
                                    ->directory('purchase-order-documents')
                                    ->visibility('private')
                                    ->downloadable()
                                    ->openable(),
                                Forms\Components\Textarea::make('notes')
                                    ->rows(2),
                            ])
                            ->itemLabel(fn (array $state): ?string => 
                                $state['type'] ? PurchaseOrderDocument::getTypes()[$state['type']] . 
                                ($state['document_number'] ? " - {$state['document_number']}" : '') : null
                            )
                            ->collapsible()
                            ->collapseAllAction(
                                fn (Forms\Components\Actions\Action $action) => $action->label('Collapse all')
                            )
                            ->expandAllAction(
                                fn (Forms\Components\Actions\Action $action) => $action->label('Expand all')
                            ),
                    ])
                    ->collapsible(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\InventoryItemsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPurchaseOrders::route('/'),
            'create' => Pages\CreatePurchaseOrder::route('/create'),
            'edit' => Pages\EditPurchaseOrder::route('/{record}/edit'),
        ];
    }

    public static function getCreatePurchaseOrderModalForm(InventoryItem $inventoryItem): array
    {
        return [
            Select::make('supplier_id')
                ->label('Supplier')
                ->options(
                    Supplier::query()
                        ->active()
                        ->orderBy('name')
                        ->pluck('name', 'id')
                )
                ->default(function () use ($inventoryItem) {
                    $preferredSupplier = $inventoryItem->preferredSupplier();
                    return $preferredSupplier?->id;
                })
                ->required()
                ->searchable(),

            Forms\Components\Select::make('status')
                ->options([
                    'draft' => 'Draft',
                    'submitted' => 'Submitted',
                ])
                ->default('draft')
                ->required(),

            Forms\Components\DatePicker::make('order_date')
                ->default(now())
                ->required(),

            Forms\Components\DatePicker::make('expected_delivery_date')
                ->after('order_date'),

            Forms\Components\Textarea::make('notes')
                ->columnSpanFull(),

        ];
    }
}
