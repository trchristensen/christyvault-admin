<?php

namespace App\Filament\Sales\Resources\SalesVisitResource\Pages;

use App\Filament\Sales\Resources\SalesVisitResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSalesVisit extends EditRecord
{
    protected static string $resource = SalesVisitResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
