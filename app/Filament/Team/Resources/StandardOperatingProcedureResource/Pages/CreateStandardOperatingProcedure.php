<?php

namespace App\Filament\Team\Resources\StandardOperatingProcedureResource\Pages;

use App\Filament\Team\Resources\StandardOperatingProcedureResource;
use Filament\Resources\Pages\CreateRecord;

class CreateStandardOperatingProcedure extends CreateRecord
{
    protected static string $resource = StandardOperatingProcedureResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return [
            ...$data,
            'owner_user_id' => $data['owner_user_id'] ?? auth()->id(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return StandardOperatingProcedureResource::getUrl('edit', ['record' => $this->record]);
    }
}
