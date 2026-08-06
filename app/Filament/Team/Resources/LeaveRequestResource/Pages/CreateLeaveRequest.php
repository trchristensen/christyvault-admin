<?php

namespace App\Filament\Team\Resources\LeaveRequestResource\Pages;

use App\Filament\Team\Resources\LeaveRequestResource;
use Filament\Resources\Pages\CreateRecord;

class CreateLeaveRequest extends CreateRecord
{
    protected static string $resource = LeaveRequestResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $employee = auth()->user()?->employee;

        abort_unless($employee, 403);

        return [
            ...$data,
            'employee_id' => $employee->getKey(),
            'status' => 'pending',
            'reviewed_by' => null,
            'review_notes' => null,
        ];
    }
}
