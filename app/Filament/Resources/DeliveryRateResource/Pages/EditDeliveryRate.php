<?php

namespace App\Filament\Resources\DeliveryRateResource\Pages;

use Filament\Actions\DeleteAction;
use App\Filament\Resources\DeliveryRateResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditDeliveryRate extends EditRecord
{
    protected static string $resource = DeliveryRateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
