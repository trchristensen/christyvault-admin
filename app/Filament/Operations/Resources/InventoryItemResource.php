<?php

namespace App\Filament\Operations\Resources;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Toggle;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Actions\Action;
use App\Console\Commands\SyncSageInventory;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Operations\Resources\InventoryItemResource\RelationManagers\SuppliersRelationManager;
use App\Filament\Operations\Resources\InventoryItemResource\Pages\ListInventoryItems;
use App\Filament\Operations\Resources\InventoryItemResource\Pages\CreateInventoryItem;
use App\Filament\Operations\Resources\InventoryItemResource\Pages\EditInventoryItem;
use App\Enums\ItemCategory;
use App\Filament\Operations\Resources\InventoryItemResource\Pages;
use App\Filament\Operations\Resources\InventoryItemResource\RelationManagers;
use App\Models\InventoryItem;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use App\Filament\Operations\Resources\PurchaseOrderResource;
use Filament\Notifications\Notification;
use App\Models\PurchaseOrder;
use App\Services\Sage100Service;
use Illuminate\Support\Facades\Auth;
use Filament\Actions\ViewAction;
use App\Enums\Department;
use Filament\Tables\Columns\ImageColumn;

class InventoryItemResource extends Resource
{
    protected static ?string $model = InventoryItem::class;
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-cube';
    protected static string | \UnitEnum | null $navigationGroup = 'Inventory Management';
    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->schema([
                        TextInput::make('sku')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        Textarea::make('description')
                            ->rows(3),
                        Select::make('category')
                            ->required()
                            ->options([

                                'Indirect Labor (Taxable)' => [
                                    ItemCategory::RM_MACHINE_AND_EQUIPMENT->value => ItemCategory::RM_MACHINE_AND_EQUIPMENT->label(),
                                    ItemCategory::FORKLIFTS->value => ItemCategory::FORKLIFTS->label(),
                                    ItemCategory::OTHER->value => ItemCategory::OTHER->label(),
                                    ItemCategory::SUPPLIES->value => ItemCategory::SUPPLIES->label(),
                                ],
                                'Shipping (Taxable)' => [
                                    ItemCategory::SH_RM_FORKLIFTS->value => ItemCategory::SH_RM_FORKLIFTS->label(),
                                    ItemCategory::SH_VEHICLES->value => ItemCategory::SH_VEHICLES->label(),
                                    ItemCategory::SH_OTHER->value => ItemCategory::SH_OTHER->label(),
                                    ItemCategory::SH_SUPPLIES->value => ItemCategory::SH_SUPPLIES->label(),
                                ],
                                'Other (Taxable)' => [
                                    ItemCategory::COST_OF_GOODS_SOLD_WILBERT->value => ItemCategory::COST_OF_GOODS_SOLD_WILBERT->label(),
                                    ItemCategory::NICHE->value => ItemCategory::NICHE->label(),
                                ],
                                'Non-Taxable Purchases' => [
                                    ItemCategory::RAW_MATERIALS->value => ItemCategory::RAW_MATERIALS->label(),
                                    ItemCategory::PRODUCTION_SUPPLIES->value => ItemCategory::PRODUCTION_SUPPLIES->label(),
                                    ItemCategory::OFFICE_SUPPLIES->value => ItemCategory::OFFICE_SUPPLIES->label(),
                                    ItemCategory::MISC->value => ItemCategory::MISC->label(),
                                ]
                            ])
                            ->searchable()
                            ->preload(),
                        TextInput::make('unit_of_measure')
                            ->required(),
                        FileUpload::make('image')
                            ->disk('r2')
                            ->visibility('public')
                            ->directory('inventory-images')
                            ->image()
                            ->maxSize(1024)
                            ->maxFiles(1)
                    ])->columns(2),

                Section::make('Stock Information')
                    ->schema([
                        TextInput::make('minimum_stock')
                            ->required()
                            ->numeric()
                            ->default(0),
                        TextInput::make('current_stock')
                            ->numeric()
                            ->default(0),
                        Select::make('department')
                            ->options(Department::getOptions())
                            ->nullable()
                            ->searchable(),
                        TextInput::make('storage_location')
                            ->maxLength(255),
                        TextInput::make('bin_number')
                            ->maxLength(255),
                        TextInput::make('sage_item_code')
                            ->maxLength(255)
                            ->helperText('The item code used in Sage 100'),
                    ])->columns(2),

                Toggle::make('active')
                    ->default(true)
                    ->dehydrateStateUsing(fn($state): string => $state ? 'true' : 'false'),
                    
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('image')
                    ->label('Image')
                    ->disk('r2')
                    ->size(40)
                    ->defaultImageUrl(url('https://r2.bytoddchristensen.com/inventory-images/image-placeholder-base.png'))
                    ->extraImgAttributes(['loading' => 'lazy']),
                TextColumn::make('sku')
                    ->label('Item #')
                    ->searchable(),
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('category')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('department')
                    ->formatStateUsing(
                        fn($state) =>
                        $state instanceof Department
                            ? $state->getLabel()
                            : ucfirst((string) $state)
                    )
                    ->sortable()
                    ->searchable(),
                TextColumn::make('current_stock')
                    ->sortable()
                    ->color(
                        fn(InventoryItem $record): string =>
                        $record->current_stock <= $record->minimum_stock
                            ? 'danger'
                            : 'success'
                    ),
                TextColumn::make('minimum_stock'),
                TextColumn::make('sage_item_code')
                    ->searchable()
                    ->sortable(),
                IconColumn::make('active')
                    ->boolean(),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('active'),
                SelectFilter::make('category'),
            ])
            ->headerActions([
                Action::make('syncAllWithSage')
                    ->label('Sync All with Sage 100')
                    ->icon('heroicon-m-arrow-path')
                    ->color('success')
                    ->action(function () {
                        $command = new SyncSageInventory();
                        $result = $command->handle(app(Sage100Service::class));

                        $notification = Notification::make()
                            ->title('Sage 100 Sync')
                            ->body($result['message']);

                        if ($result['status'] === 'success') {
                            $notification->success();
                        } else {
                            $notification->warning()
                                ->body(implode("\n", array_merge([$result['message']], $result['details'])));
                        }

                        $notification->send();
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Sync with Sage 100')
                    ->modalDescription('This will update the inventory levels from Sage 100. This operation is read-only and will not modify Sage 100 data.')
                    ->modalSubmitActionLabel('Yes, sync now'),
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make(),
                    Action::make('syncWithSage')
                        ->label('Sync with Sage 100')
                        ->icon('heroicon-o-arrow-path')
                        ->action(function (InventoryItem $record) {
                            $result = $record->syncWithSage();
                            Notification::make()
                                ->title('Synced with Sage 100')
                                ->body($result['message'])
                                ->success()
                                ->send();
                        })
                        ->visible(fn(InventoryItem $record) => $record->sage_item_code !== null),
                ])
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            SuppliersRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInventoryItems::route('/'),
            'create' => CreateInventoryItem::route('/create'),
            'edit' => EditInventoryItem::route('/{record}/edit'),
        ];
    }
}
