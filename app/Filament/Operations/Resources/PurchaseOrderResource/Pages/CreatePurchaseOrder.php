<?php

namespace App\Filament\Operations\Resources\PurchaseOrderResource\Pages;

use App\Filament\Operations\Resources\PurchaseOrderResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreatePurchaseOrder extends CreateRecord
{
    protected static string $resource = PurchaseOrderResource::class;
}
