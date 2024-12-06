<?php

namespace App\Filament\Operations\Resources\InventoryItemResource\Pages;

use App\Filament\Operations\Resources\InventoryItemResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateInventoryItem extends CreateRecord
{
    protected static string $resource = InventoryItemResource::class;
}
