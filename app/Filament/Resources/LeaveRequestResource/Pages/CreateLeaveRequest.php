<?php

namespace App\Filament\Resources\LeaveRequestResource\Pages;

use App\Filament\Resources\LeaveRequestResource;
use Filament\Resources\Pages\CreateRecord;

class CreateLeaveRequest extends CreateRecord
{
    protected static string $resource = LeaveRequestResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $status = $data['status'] ?? 'pending';

        return [
            ...$data,
            'reviewed_by' => $status === 'pending' ? null : auth()->id(),
        ];
    }
}
