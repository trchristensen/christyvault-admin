<?php

namespace App\Filament\Team\Resources\EmployeeProgramResource\Pages;

use App\Filament\Team\Resources\EmployeeProgramResource;
use Filament\Resources\Pages\CreateRecord;

class CreateEmployeeProgram extends CreateRecord
{
    protected static string $resource = EmployeeProgramResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return [
            ...$data,
            'owner_user_id' => $data['owner_user_id'] ?? auth()->id(),
            'status' => 'draft',
        ];
    }

    protected function getRedirectUrl(): string
    {
        return EmployeeProgramResource::getUrl('edit', ['record' => $this->record]);
    }
}
