<?php

namespace App\Filament\Resources\DeliveryRateResource\Pages;

use Filament\Actions\CreateAction;
use App\Filament\Resources\DeliveryRateResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListDeliveryRates extends ListRecords
{
    protected static string $resource = DeliveryRateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
