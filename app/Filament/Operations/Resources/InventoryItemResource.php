<?php

namespace App\Filament\Operations\Resources;

use App\Enums\ItemCategory;
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
use App\Services\Sage100Service;
use Illuminate\Support\Facades\Auth;
use Filament\Actions\ViewAction;
use App\Enums\Department;
use Filament\Tables\Columns\ImageColumn;

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
                        Forms\Components\TextInput::make('unit_of_measure')
                            ->required(),
                        Forms\Components\FileUpload::make('image')
                            ->disk('r2')
                            ->visibility('public')
                            ->directory('inventory-images')
                            ->image()
                            ->maxSize(1024)
                            ->maxFiles(1)
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
                        Forms\Components\Select::make('department')
                            ->options(Department::getOptions())
                            ->nullable()
                            ->searchable(),
                        Forms\Components\TextInput::make('storage_location')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('bin_number')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('sage_item_code')
                            ->maxLength(255)
                            ->helperText('The item code used in Sage 100'),
                    ])->columns(2),

                Forms\Components\Toggle::make('active')
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
                    ->defaultImageUrl(url('/images/no-image.png'))
                    ->extraImgAttributes(['loading' => 'lazy']),
                Tables\Columns\TextColumn::make('sku')
                    ->searchable(),
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('category')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('department')
                    ->formatStateUsing(
                        fn($state) =>
                        $state instanceof Department
                            ? $state->getLabel()
                            : ucfirst((string) $state)
                    )
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('current_stock')
                    ->sortable()
                    ->color(
                        fn(InventoryItem $record): string =>
                        $record->current_stock <= $record->minimum_stock
                            ? 'danger'
                            : 'success'
                    ),
                Tables\Columns\TextColumn::make('minimum_stock'),
                Tables\Columns\TextColumn::make('sage_item_code')
                    ->searchable()
                    ->sortable(),
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
            ->headerActions([
                Tables\Actions\Action::make('syncAllWithSage')
                    ->label('Sync All with Sage 100')
                    ->icon('heroicon-m-arrow-path')
                    ->color('success')
                    ->action(function () {
                        $command = new \App\Console\Commands\SyncSageInventory();
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
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('syncWithSage')
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
