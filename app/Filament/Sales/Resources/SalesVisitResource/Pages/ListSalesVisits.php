<?php

namespace App\Filament\Sales\Resources\SalesVisitResource\Pages;

use Filament\Actions\CreateAction;
use App\Filament\Sales\Resources\SalesVisitResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSalesVisits extends ListRecords
{
    protected static string $resource = SalesVisitResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
