<?php

namespace App\Filament\Operations\Resources;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Filters\SelectFilter;
use Filament\Actions\ViewAction;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\BulkAction;
use Filament\Schemas\Schema;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\FileUpload;
use App\Filament\Operations\Resources\PurchaseOrderResource\RelationManagers\InventoryItemsRelationManager;
use App\Filament\Operations\Resources\PurchaseOrderResource\Pages\ListPurchaseOrders;
use App\Filament\Operations\Resources\PurchaseOrderResource\Pages\CreatePurchaseOrder;
use App\Filament\Operations\Resources\PurchaseOrderResource\Pages\EditPurchaseOrder;
use App\Filament\Operations\Resources\PurchaseOrderResource\Pages;
use App\Filament\Operations\Resources\PurchaseOrderResource\RelationManagers;
use App\Models\PurchaseOrder;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
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
use Filament\Infolist\Infolist;
use Filament\Infolist\Components\Section;
use Filament\Infolist\Components\Grid;
use Filament\Infolist\Components\TextEntry;
use Filament\Infolist\Components\IconEntry;
use Filament\Infolist\Components\RepeatableEntry;

class PurchaseOrderResource extends Resource
{
    protected static ?string $model = PurchaseOrder::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('supplier.name')
                    ->sortable()
                    ->searchable(),

                BadgeColumn::make('status')
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
                    ])
                    ->sortable(),

                TextColumn::make('order_date')
                    ->date()
                    ->sortable(),

                TextColumn::make('expected_delivery_date')
                    ->date()
                    ->sortable(),

                TextColumn::make('received_date')
                    ->date()
                    ->sortable(),

                TextColumn::make('total_amount')
                    ->money('USD')
                    ->sortable(),

                TextColumn::make('items.name')
                    ->label('Items')
                    ->listWithLineBreaks()
                    ->limitList(3)
                    ->expandableLimitedList()
                    ->searchable()
                    ->toggleable(),

                IconColumn::make('is_liner_load')
                    ->label('Liner Load')
                    ->boolean()
                    ->visible(fn (?Model $record): bool => 
                        $record?->supplier?->name === 'Wilbert'
                    ),

              

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->groups([
                Group::make('status')
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
            ->defaultSort('status', 'asc')
            ->modifyQueryUsing(function (Builder $query) {
                // Add a custom sort order for status
                $query->orderByRaw("
                    CASE 
                        WHEN status = 'completed' THEN 6
                        WHEN status = 'cancelled' THEN 5
                        WHEN status = 'awaiting_invoice' THEN 4
                        WHEN status = 'received' THEN 3
                        WHEN status = 'submitted' THEN 2
                        WHEN status = 'draft' THEN 1
                        ELSE 7
                    END
                ");
            })
            ->filters([
                SelectFilter::make('status')
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
            ->recordActions([
                ViewAction::make()
                    ->url(null)
                    ->modalContent(fn (PurchaseOrder $record) => view('livewire.view-purchase-order', [
                        'record' => $record,
                    ]))
                    ->modalSubmitAction(false)
                    ->modalCancelAction(false)
                    ->modalHeading('Purchase Order Details')
                    ->modalFooterActions([
                        EditAction::make()
                            ->url(fn (PurchaseOrder $record): string => 
                                static::getUrl('edit', ['record' => $record])
                            ),
                        DeleteAction::make(),
                    ])
                    ->stickyModalHeader()
                    ->stickyModalFooter(),
                EditAction::make(),
                Action::make('merge')
                    ->icon('heroicon-o-arrow-path-rounded-square')
                    ->label('Merge')
                    ->schema([
                        Select::make('merge_with')
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
                        PurchaseOrder::where('supplier_id', $record->supplier_id)
                            ->where('status', 'draft')
                            ->where('id', '!=', $record->id)
                            ->exists()
                    )
                    ->requiresConfirmation(),
            ])
            ->recordAction('view')
            ->recordUrl(null)
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    BulkAction::make('mergeBulk')
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
            ])
            ->recordAction('view');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('supplier_id')
                    ->relationship('supplier', 'name')
                    ->required()
                    ->searchable()
                    ->preload()
                    ->live(),

                Toggle::make('is_liner_load')
                    ->live()
                    ->label('Liner Load')
                    ->dehydrateStateUsing(fn($state): string => $state ? 'true' : 'false')
                    ->default(false)
                    ->visible(fn (Get $get): bool => 
                        Supplier::find($get('supplier_id'))?->name === 'Wilbert'
                    )
                    ->default(false),

                Select::make('status')
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

                DatePicker::make('order_date')
                    ->label(fn (Get $get) => $get('is_liner_load') ? 'Order Deadline' : 'Order Date')
                    ->live()
                    ->default(now())
                    ->required(),

                DatePicker::make('expected_delivery_date'),

                DatePicker::make('received_date'),

                TextInput::make('total_amount')
                    ->required()
                    ->numeric()
                    ->prefix('$')
                    ->minValue(0)
                    ->default(0),

                Textarea::make('notes')
                    ->columnSpanFull(),

                Hidden::make('created_by_user_id')
                    ->default(fn() => Auth::id())
                    ->dehydrated(fn($state) => filled($state)),

                \Filament\Schemas\Components\Section::make('Documents')
                    ->schema([
                        Repeater::make('documents')
                            ->relationship()
                            ->schema([
                                Select::make('type')
                                    ->options(PurchaseOrderDocument::getTypes())
                                    ->required(),
                                TextInput::make('document_number')
                                    ->label('Document Number')
                                    ->helperText('e.g. Invoice number, BOL number, Quote number')
                                    ->maxLength(255),
                                FileUpload::make('file_path')
                                    ->label('Document File')
                                    ->disk('r2')
                                    ->directory('purchase-order-documents')
                                    ->visibility('private')
                                    ->downloadable()
                                    ->openable()
                                    ->acceptedFileTypes([
                                        'application/pdf',
                                        'image/png',
                                        'image/jpeg',
                                        'image/jpg',
                                        'image/gif',
                                        'image/webp',
                                    ]),
                                Textarea::make('notes')
                                    ->rows(2),
                            ])
                            ->itemLabel(fn (array $state): ?string => 
                                $state['type'] ? PurchaseOrderDocument::getTypes()[$state['type']] . 
                                ($state['document_number'] ? " - {$state['document_number']}" : '') : null
                            )
                            ->collapsible()
                            ->collapseAllAction(
                                fn (Action $action) => $action->label('Collapse all')
                            )
                            ->expandAllAction(
                                fn (Action $action) => $action->label('Expand all')
                            ),
                    ])
                    ->collapsible(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            InventoryItemsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPurchaseOrders::route('/'),
            'create' => CreatePurchaseOrder::route('/create'),
            'edit' => EditPurchaseOrder::route('/{record}/edit'),
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

            Select::make('status')
                ->options([
                    'draft' => 'Draft',
                    'submitted' => 'Submitted',
                ])
                ->default('draft')
                ->required(),

            DatePicker::make('order_date')
                ->default(now())
                ->required(),

            DatePicker::make('expected_delivery_date')
                ->after('order_date'),

            Textarea::make('notes')
                ->columnSpanFull(),

        ];
    }
}
