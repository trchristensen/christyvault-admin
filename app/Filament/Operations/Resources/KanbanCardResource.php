<?php

namespace App\Filament\Operations\Resources;

use App\Filament\Operations\Resources\KanbanCardResource\Pages;
use App\Filament\Operations\Resources\KanbanCardResource\RelationManagers;
use App\Models\KanbanCard;
use App\Models\InventoryItem;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class KanbanCardResource extends Resource
{
    protected static ?string $model = KanbanCard::class;
    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?string $navigationGroup = 'Inventory Management';
    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('inventory_item_id')
                    ->relationship('inventoryItem', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                Forms\Components\TextInput::make('bin_number')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('bin_location')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('reorder_point')
                    ->numeric()
                    ->required()
                    ->default(0),
                Forms\Components\Select::make('status')
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
                Tables\Columns\TextColumn::make('inventoryItem.name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('bin_number')
                    ->searchable(),
                Tables\Columns\TextColumn::make('bin_location')
                    ->searchable(),
                Tables\Columns\TextColumn::make('reorder_point')
                    ->numeric(),
                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'success' => KanbanCard::STATUS_ACTIVE,
                        'warning' => KanbanCard::STATUS_PENDING_ORDER,
                        'info' => KanbanCard::STATUS_ORDERED,
                    ])
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        KanbanCard::STATUS_ACTIVE => 'Active',
                        KanbanCard::STATUS_PENDING_ORDER => 'Pending Order',
                        KanbanCard::STATUS_ORDERED => 'Ordered',
                        default => $state,
                    }),
                Tables\Columns\TextColumn::make('last_scanned_at')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('scannedBy.name')
                    ->label('Last Scanned By'),
            ])
            ->defaultGroup('status')
            ->groups([
                Tables\Grouping\Group::make('status')
                    ->label('Status')
                    ->getTitleFromRecordUsing(fn (KanbanCard $record): string => match ($record->status) {
                        KanbanCard::STATUS_ACTIVE => 'Active Cards',
                        KanbanCard::STATUS_PENDING_ORDER => 'Pending Order Cards',
                        KanbanCard::STATUS_ORDERED => 'Ordered Cards',
                        default => $record->status,
                    })
                    ->collapsible()
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        KanbanCard::STATUS_ACTIVE => 'Active',
                        KanbanCard::STATUS_PENDING_ORDER => 'Pending Order',
                        KanbanCard::STATUS_ORDERED => 'Ordered',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('scan')
                    ->icon('heroicon-o-qr-code')
                    ->action(fn (KanbanCard $record) => $record->markAsScanned())
                    ->requiresConfirmation()
                    ->visible(fn (KanbanCard $record) => $record->canBeScanned()),
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
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListKanbanCards::route('/'),
            'create' => Pages\CreateKanbanCard::route('/create'),
            'edit' => Pages\EditKanbanCard::route('/{record}/edit'),
        ];
    }
}
