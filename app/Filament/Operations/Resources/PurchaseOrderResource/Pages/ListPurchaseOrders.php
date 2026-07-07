<?php

namespace App\Filament\Operations\Resources\PurchaseOrderResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Schemas\Components\Tabs\Tab;
use App\Filament\Operations\Resources\PurchaseOrderResource;
use Filament\Actions;
use Illuminate\Database\Eloquent\Builder;
use Filament\Resources\Pages\ListRecords;

class ListPurchaseOrders extends ListRecords
{
    protected static string $resource = PurchaseOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
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
