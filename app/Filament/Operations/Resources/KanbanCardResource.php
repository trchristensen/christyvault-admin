<?php

namespace App\Filament\Operations\Resources;

use Filament\Schemas\Schema;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Filters\SelectFilter;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\BulkAction;
use App\Filament\Operations\Resources\KanbanCardResource\Pages\ListKanbanCards;
use App\Filament\Operations\Resources\KanbanCardResource\Pages\CreateKanbanCard;
use App\Filament\Operations\Resources\KanbanCardResource\Pages\EditKanbanCard;
use App\Filament\Operations\Resources\KanbanCardResource\Pages;
use App\Filament\Operations\Resources\KanbanCardResource\RelationManagers;
use App\Models\KanbanCard;
use App\Models\InventoryItem;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Columns\ImageColumn;

class KanbanCardResource extends Resource
{
    protected static ?string $model = KanbanCard::class;
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static string | \UnitEnum | null $navigationGroup = 'Inventory Management';
    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('inventory_item_id')
                    ->relationship('inventoryItem', 'name')
                    // show the inventory item sku in the dropdown
                    ->options(fn(Get $get): Collection => InventoryItem::query()
                        ->where('name', 'like', '%' . $get('name') . '%')
                        ->pluck('sku', 'id'))
                    ->searchable()
                    ->preload()
                    ->required()
                    ->live()
                    ->afterStateUpdated(function ($state, Set $set) {
                        if (!$state) return;

                        $inventoryItem = InventoryItem::find($state);
                        if (!$inventoryItem) return;

                        // Auto-populate fields from inventory item
                        $set('reorder_point', $inventoryItem->minimum_stock);
                        $set('unit_of_measure', $inventoryItem->unit_of_measure);
                    }),
                Textarea::make('description')
                    ->maxLength(255)
                    ->columnSpanFull(),

                TextInput::make('reorder_point')
                    ->numeric()
                    ->required()
                    ->default(function (Get $get) {
                        $inventoryItem = InventoryItem::find($get('inventory_item_id'));
                        return $inventoryItem?->minimum_stock ?? 0;
                    }),

                TextInput::make('unit_of_measure')
                    ->required()
                    ->afterStateHydrated(function ($component, $state, Set $set) {
                        if ($state) return; // If there's already a value, don't override it

                        $record = $component->getRecord();
                        if (!$record?->inventoryItem) return;

                        $set('unit_of_measure', $record->inventoryItem->unit_of_measure);
                    }),

                Select::make('status')
                    ->options([
                        KanbanCard::STATUS_ACTIVE => 'Active',
                        KanbanCard::STATUS_PENDING_ORDER => 'Pending Order',
                        KanbanCard::STATUS_ORDERED => 'Ordered',
                    ])
                    ->default(KanbanCard::STATUS_ACTIVE)
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('inventoryItem.image')
                    ->label('Image')
                    ->disk('r2')
                    ->circular()
                    ->defaultImageUrl(url('https://r2.bytoddchristensen.com/inventory-images/image-placeholder-base.png'))
                    ->size(40)
                    ->extraImgAttributes(['loading' => 'lazy']),
                     TextColumn::make('inventoryItem.sku')
                    ->searchable()
                    ->label('Item #')
                    ->sortable(),
                TextColumn::make('inventoryItem.name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('description')
                    ->limit(30)
                    ->searchable(),
                TextColumn::make('reorder_point')
                    ->numeric(),
                TextColumn::make('unit_of_measure')
                    ->label('Unit of Measure'),
                BadgeColumn::make('status')
                    ->colors([
                        'success' => KanbanCard::STATUS_ACTIVE,
                        'warning' => KanbanCard::STATUS_PENDING_ORDER,
                        'info' => KanbanCard::STATUS_ORDERED,
                    ])
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        KanbanCard::STATUS_ACTIVE => 'Active',
                        KanbanCard::STATUS_PENDING_ORDER => 'Pending Order',
                        KanbanCard::STATUS_ORDERED => 'Ordered',
                        default => $state,
                    }),
                TextColumn::make('last_scanned_at')
                    ->label('Last Scanned')
                    ->dateTime()
                    ->sortable(),
                // Tables\Columns\TextColumn::make('scannedBy.name')
                //     ->label('Last Scanned By'),
                // Tables\Columns\ViewColumn::make('qr_code')
                //     ->label('QR Code')
                //     ->view('filament.tables.columns.qr-code'),
            ])
            ->defaultGroup('status')
            ->defaultSort('status', 'asc')
            ->modifyQueryUsing(function (Builder $query) {
                // Add a custom sort order for status
                $query->orderByRaw("
                    CASE 
                        WHEN status = 'active' THEN 3
                        WHEN status = 'pending_order' THEN 2
                        WHEN status = 'ordered' THEN 1
                        ELSE 4
                    END
                ");
            })
            ->groups([
                Group::make('status')
                    ->label('Status')
                    ->orderQueryUsing(fn (Builder $query, string $direction) => $query->orderByRaw("
                        CASE 
                            WHEN status = 'active' THEN 3
                            WHEN status = 'pending_order' THEN 2
                            WHEN status = 'ordered' THEN 1
                            ELSE 4
                        END
                    "))
                    ->collapsible()
            ])
       
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        KanbanCard::STATUS_ACTIVE => 'Active',
                        KanbanCard::STATUS_PENDING_ORDER => 'Pending Order',
                        KanbanCard::STATUS_ORDERED => 'Ordered',
                    ]),
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make(),
                    Action::make('scan')
                        ->icon('heroicon-o-qr-code')
                        ->action(fn(KanbanCard $record) => $record->markAsScanned())
                        ->requiresConfirmation()
                        ->visible(fn(KanbanCard $record) => $record->canBeScanned()),
                    Action::make('setActive')
                        ->label('Set Active')
                        ->icon('heroicon-o-check-circle')
                        ->action(fn(KanbanCard $record) => $record->update(['status' => KanbanCard::STATUS_ACTIVE]))
                        ->requiresConfirmation()
                        ->visible(fn(KanbanCard $record) => $record->status !== KanbanCard::STATUS_ACTIVE)
                        ->successNotification(
                            Notification::make()
                                ->title('Kanban card status set to active')
                                ->success()
                        ),
                    Action::make('printKanban')
                        ->label('Print Kanban')
                        ->icon('heroicon-o-printer')
                        ->url(fn(KanbanCard $record): string => 
                            route('kanban-cards.print', [
                                'kanbanCard' => $record,
                                'size' => request('size', 'standard'),
                                'type' => request('type', 'storage')
                            ]))
                        ->openUrlInNewTab(),
                    Action::make('printLabel')
                        ->label('Print Label')
                        ->icon('heroicon-o-tag')
                        ->url(fn(KanbanCard $record): string => 
                            route('kanban-cards.print-label', $record))
                        ->openUrlInNewTab(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->requiresConfirmation(),
                    BulkAction::make('printLabels')
                        ->label('Print Labels')
                        ->icon('heroicon-o-printer')
                        ->action(function (Collection $records) {
                            return redirect()->route('kanban-cards.print-labels-bulk', [
                                'kanbanCards' => $records->pluck('id')->join(','),
                                'size' => request('size', 'large')
                            ]);
                        })
                        ->openUrlInNewTab()
                        ->visible(fn () => true)
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
            'index' => ListKanbanCards::route('/'),
            'create' => CreateKanbanCard::route('/create'),
            'edit' => EditKanbanCard::route('/{record}/edit'),
        ];
    }
}
