<?php

namespace App\Filament\Resources\TripResource\Pages;

use App\Enums\TripStatus;
use App\Filament\Resources\TripResource;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;
use Filament\Resources\Pages\ListRecords;

class ListTrips extends ListRecords
{
    protected static string $resource = TripResource::class;

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
                ->modifyQueryUsing(fn(Builder $query) => $query->whereNotIn('status', [TripStatus::CANCELLED->value, TripStatus::COMPLETED->value])),
            'unassigned' => Tab::make()
                ->modifyQueryUsing(fn(Builder $query) => $query->whereNull('driver_id')),
            'all' => Tab::make(),
            'inactive' => Tab::make()
                ->modifyQueryUsing(fn(Builder $query) => $query->whereIn('status', [TripStatus::CANCELLED->value, TripStatus::COMPLETED->value])),
        ];
    }
}
