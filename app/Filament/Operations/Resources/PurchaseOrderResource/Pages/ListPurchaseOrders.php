<?php

namespace App\Filament\Operations\Resources\PurchaseOrderResource\Pages;

use App\Filament\Operations\Resources\PurchaseOrderResource;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;
use Filament\Resources\Pages\ListRecords;

class ListPurchaseOrders extends ListRecords
{
    protected static string $resource = PurchaseOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    public function getTabs(): array
    {
        return [
            'incomplete' => Tab::make('Incomplete')
                ->modifyQueryUsing(fn(Builder $query) => $query->whereNotIn('status', ['completed', 'cancelled'])),
            'completed' => Tab::make('Completed')
                ->modifyQueryUsing(fn(Builder $query) => $query->whereIn('status', ['completed', 'cancelled'])),
            'all' => Tab::make('All'),
        ];
    }
}
