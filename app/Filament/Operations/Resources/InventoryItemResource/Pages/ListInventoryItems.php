<?php

namespace App\Filament\Operations\Resources\InventoryItemResource\Pages;

use Filament\Actions\CreateAction;
use App\Filament\Operations\Resources\InventoryItemResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListInventoryItems extends ListRecords
{
    protected static string $resource = InventoryItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
