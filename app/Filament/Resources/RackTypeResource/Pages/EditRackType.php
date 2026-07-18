<?php

namespace App\Filament\Resources\RackTypeResource\Pages;

use App\Filament\Resources\RackTypeResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditRackType extends EditRecord
{
    protected static string $resource = RackTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
