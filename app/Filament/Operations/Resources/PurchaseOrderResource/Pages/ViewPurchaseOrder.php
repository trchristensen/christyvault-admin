<?php

namespace App\Filament\Operations\Resources\PurchaseOrderResource\Pages;

use App\Filament\Operations\Resources\PurchaseOrderResource;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\RepeatableEntry;

class ViewPurchaseOrder extends ViewRecord
{
    protected static string $resource = PurchaseOrderResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Order Details')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('supplier.name')
                                    ->label('Supplier'),
                                TextEntry::make('status')
                                    ->badge()
                                    ->color(fn (string $state): string => match ($state) {
                                        'draft' => 'warning',
                                        'submitted' => 'primary',
                                        'received' => 'success',
                                        'awaiting_invoice' => 'info',
                                        'cancelled' => 'danger',
                                        'completed' => 'success',
                                        default => 'gray',
                                    }),
                                IconEntry::make('is_liner_load')
                                    ->label('Liner Load')
                                    ->boolean()
                                    ->visible(fn ($record) => $record->supplier?->name === 'Wilbert'),
                            ]),
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('order_date')
                                    ->label('Order Date')
                                    ->date(),
                                TextEntry::make('expected_delivery_date')
                                    ->label('Expected Delivery')
                                    ->date(),
                                TextEntry::make('received_date')
                                    ->label('Received Date')
                                    ->date(),
                            ]),
                        TextEntry::make('total_amount')
                            ->label('Total Amount')
                            ->money('USD'),
                        TextEntry::make('notes')
                            ->markdown()
                            ->columnSpanFull(),
                    ])
                    ->columns(3),

                Section::make('Items')
                    ->schema([
                        RepeatableEntry::make('items')
                            ->schema([
                                Grid::make(4)
                                    ->schema([
                                        TextEntry::make('name')
                                            ->label('Item'),
                                        TextEntry::make('pivot.quantity')
                                            ->label('Quantity'),
                                        TextEntry::make('pivot.unit_price')
                                            ->label('Unit Price')
                                            ->money('USD'),
                                        TextEntry::make('pivot.total_price')
                                            ->label('Total Price')
                                            ->money('USD'),
                                    ]),
                            ])
                            ->columnSpanFull(),
                    ]),

                Section::make('Additional Information')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('createdBy.name')
                                    ->label('Created By'),
                                TextEntry::make('created_at')
                                    ->label('Created At')
                                    ->dateTime(),
                            ]),
                    ])
                    ->collapsed(),
            ]);
    }
} 