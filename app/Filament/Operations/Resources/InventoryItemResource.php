<?php

namespace App\Filament\Operations\Resources;

use App\Filament\Operations\Resources\InventoryItemResource\Pages;
use App\Filament\Operations\Resources\InventoryItemResource\RelationManagers;
use App\Models\InventoryItem;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Actions\Action;
use App\Filament\Operations\Resources\PurchaseOrderResource;
use Filament\Notifications\Notification;
use App\Models\PurchaseOrder;
use Illuminate\Support\Facades\Auth;
use Filament\Actions\ViewAction;

class InventoryItemResource extends Resource
{
    protected static ?string $model = InventoryItem::class;
    protected static ?string $navigationIcon = 'heroicon-o-cube';
    protected static ?string $navigationGroup = 'Inventory Management';
    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make()
                    ->schema([
                        Forms\Components\TextInput::make('sku')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\Textarea::make('description')
                            ->rows(3),
                        Forms\Components\Select::make('category')
                            ->required()
                            ->searchable()
                            ->preload(),
                        Forms\Components\TextInput::make('unit_of_measure')
                            ->required(),
                    ])->columns(2),

                Forms\Components\Section::make('Stock Information')
                    ->schema([
                        Forms\Components\TextInput::make('minimum_stock')
                            ->required()
                            ->numeric()
                            ->default(0),
                        Forms\Components\TextInput::make('current_stock')
                            ->numeric()
                            ->default(0),
                        Forms\Components\TextInput::make('reorder_lead_time')
                            ->numeric()
                            ->suffix('days'),
                        Forms\Components\TextInput::make('storage_location')
                            ->maxLength(255),
                    ])->columns(2),

                Forms\Components\Toggle::make('active')
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('sku')
                    ->searchable(),
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('category')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('current_stock')
                    ->sortable()
                    ->color(
                        fn(InventoryItem $record): string =>
                        $record->current_stock <= $record->minimum_stock
                            ? 'danger'
                            : 'success'
                    ),
                Tables\Columns\TextColumn::make('minimum_stock'),
                Tables\Columns\IconColumn::make('active')
                    ->boolean(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('active'),
                Tables\Filters\SelectFilter::make('category'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Action::make('create_purchase_order')
                    ->label('Create PO')
                    ->icon('heroicon-o-shopping-cart')
                    ->modalHeading(fn($record) => "Create Purchase Order for {$record->name}")
                    ->form(fn($record) => PurchaseOrderResource::getCreatePurchaseOrderModalForm($record))
                    ->action(function (array $data, InventoryItem $record): void {
                        // Create the purchase order
                        $purchaseOrder = PurchaseOrder::create([
                            'supplier_id' => $data['supplier_id'],
                            'status' => $data['status'],
                            'order_date' => $data['order_date'],
                            'expected_delivery_date' => $data['expected_delivery_date'],
                            'notes' => $data['notes'],
                            'created_by_user_id' => Auth::id(),
                        ]);

                        // Create the purchase order item
                        $purchaseOrder->items()->create([
                            'inventory_item_id' => $record->id,
                            'quantity' => $data['items'][0]['quantity'],
                            'unit_price' => $data['items'][0]['unit_price'],
                            'notes' => $data['items'][0]['notes'] ?? null,
                        ]);

                        // Update total amount
                        $purchaseOrder->update([
                            'total_amount' => $data['items'][0]['quantity'] * $data['items'][0]['unit_price'],
                        ]);

                        Notification::make()
                            ->title('Purchase order created successfully')
                            ->success()
                            ->send();
                    })
                    ->visible(fn($record) => $record->needsReorder()),
                Action::make('syncWithSage')
                    ->label('Sync with Sage 100')
                    ->icon('heroicon-o-arrow-path')
                    ->action(function (InventoryItem $record) {

                        try {
                            $result = $record->syncWithSage();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Error syncing with Sage 100')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }

                        if ($result['status'] === 'error') {
                            return Notification::make()
                                ->title('Error syncing with Sage 100')
                                ->body($result['message'])
                                ->danger()
                                ->send();
                        }

                        Notification::make()
                            ->title('Synced with Sage 100')
                            ->body($result['message'])
                            ->success()
                            ->send();
                    })
                // ->visible(fn(InventoryItem $record) => $record->sage_item_code !== null),
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
            RelationManagers\SuppliersRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInventoryItems::route('/'),
            'create' => Pages\CreateInventoryItem::route('/create'),
            'edit' => Pages\EditInventoryItem::route('/{record}/edit'),
        ];
    }
}
