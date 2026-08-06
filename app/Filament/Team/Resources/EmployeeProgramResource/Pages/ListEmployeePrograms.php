<?php

namespace App\Filament\Team\Resources\EmployeeProgramResource\Pages;

use App\Filament\Team\Resources\EmployeeProgramResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListEmployeePrograms extends ListRecords
{
    protected static string $resource = EmployeeProgramResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->visible(fn (): bool => auth()->user()?->canManagePrograms() ?? false),
        ];
    }
}
