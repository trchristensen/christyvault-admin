<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;
use Filament\Resources\Pages\ListRecords;
use App\Enums\OrderStatus;


class ListOrders extends ListRecords
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    public function getTabs(): array
    {
        return [
            'active' => Tab::make()
                ->modifyQueryUsing(fn(Builder $query) => $query->whereNotIn('status', [
                    OrderStatus::DELIVERED->value,
                    OrderStatus::CANCELLED->value,
                    OrderStatus::COMPLETED->value
                ])),
            'unassigned' => Tab::make()
                ->modifyQueryUsing(fn(Builder $query) => $query->whereNull('trip_id')),
            'all' => Tab::make(),
            'inactive' => Tab::make()
                ->modifyQueryUsing(fn(Builder $query) => $query->whereIn('status', [
                    OrderStatus::DELIVERED->value,
                    OrderStatus::CANCELLED->value,
                    OrderStatus::COMPLETED->value
                ])),
        ];
    }
}
