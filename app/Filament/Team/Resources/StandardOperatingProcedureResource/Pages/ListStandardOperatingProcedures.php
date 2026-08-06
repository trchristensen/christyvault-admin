<?php

namespace App\Filament\Team\Resources\StandardOperatingProcedureResource\Pages;

use App\Filament\Team\Resources\StandardOperatingProcedureResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListStandardOperatingProcedures extends ListRecords
{
    protected static string $resource = StandardOperatingProcedureResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->visible(fn (): bool => auth()->user()?->canManageProcedures() ?? false),
        ];
    }
}
