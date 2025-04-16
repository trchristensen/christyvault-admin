<?php

namespace App\Livewire;

use App\Models\PurchaseOrder;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Infolists\Concerns\InteractsWithInfolists;
use Filament\Infolists\Contracts\HasInfolists;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\IconEntry;
use Livewire\Component;

class ViewPurchaseOrder extends Component implements HasForms, HasInfolists
{
    use InteractsWithForms;
    use InteractsWithInfolists;

    public PurchaseOrder $record;

    public function mount(PurchaseOrder $record): void
    {
        $this->record = $record;
    }

    protected function getInfolist(): Infolist
    {
        return Infolist::make()
            ->record($this->record)
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
                        TextEntry::make('items')
                            ->listable()
                            ->formatStateUsing(fn ($state) => $state->map(fn ($item) => 
                                "{$item->name} - {$item->pivot->quantity} @ \${$item->pivot->unit_price} = \${$item->pivot->total_price}"
                            )),
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

    public function render()
    {
        return view('livewire.view-purchase-order');
    }
}
