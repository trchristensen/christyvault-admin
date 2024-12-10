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

class PurchaseOrderResource extends Resource
{
    protected static ?string $model = PurchaseOrder::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('supplier_id')
                    ->relationship('supplier', 'name')
                    ->required()
                    ->searchable()
                    ->preload(),

                Forms\Components\Select::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'submitted' => 'Submitted',
                        'received' => 'Received',
                        'awaiting_invoice' => 'Awaiting Invoice',
                        'cancelled' => 'Cancelled',
                    ])
                    ->default('draft')
                    ->required(),

                Forms\Components\DateTimePicker::make('order_date')
                    ->default(now())
                    ->required(),

                Forms\Components\DateTimePicker::make('expected_delivery_date')
                    ->after('order_date'),

                Forms\Components\DateTimePicker::make('received_date')
                    ->after('order_date')
                    ->visible(fn(Get $get): bool => $get('status') === 'received'),

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
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('supplier.name')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\BadgeColumn::make('status')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'draft' => 'Draft',
                        'submitted' => 'Submitted',
                        'received' => 'Received',
                        'awaiting_invoice' => 'Awaiting Invoice',
                        'cancelled' => 'Cancelled',
                        default => $state,
                    })
                    ->colors([
                        'warning' => 'draft',
                        'primary' => 'submitted',
                        'success' => 'received',
                        'danger' => 'cancelled',
                        'info' => 'awaiting_invoice',
                    ]),

                Tables\Columns\TextColumn::make('order_date')
                    ->dateTime()
                    ->sortable(),

                Tables\Columns\TextColumn::make('expected_delivery_date')
                    ->dateTime()
                    ->sortable(),

                Tables\Columns\TextColumn::make('received_date')
                    ->dateTime()
                    ->sortable(),

                Tables\Columns\TextColumn::make('total_amount')
                    ->money('USD')
                    ->sortable(),

                Tables\Columns\TextColumn::make('createdBy.name')
                    ->label('Created By'),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
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

            Forms\Components\DateTimePicker::make('order_date')
                ->default(now())
                ->required(),

            Forms\Components\DateTimePicker::make('expected_delivery_date')
                ->after('order_date'),

            Forms\Components\Textarea::make('notes')
                ->columnSpanFull(),

        ];
    }
}
