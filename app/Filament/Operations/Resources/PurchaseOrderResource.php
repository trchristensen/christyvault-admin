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
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
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
                    ->label('Liner Load')
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

                Forms\Components\Section::make('Invoice Information')
                    ->schema([
                        Forms\Components\TextInput::make('invoice_number')
                            ->label('Vendor Invoice Number')
                            ->maxLength(255),
                        Forms\Components\FileUpload::make('invoice_image_path')
                            ->label('Invoice Image')
                            ->directory('invoices')
                            ->visibility('private')
                            ->downloadable()
                            ->openable(),
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
