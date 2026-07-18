<?php

namespace App\Filament\Resources\RackTypeResource\Pages;

use App\Filament\Resources\RackTypeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRackTypes extends ListRecords
{
    protected static string $resource = RackTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
