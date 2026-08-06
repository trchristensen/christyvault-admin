<?php

namespace App\Filament\Team\Resources\LeaveRequestResource\Pages;

use App\Filament\Team\Resources\LeaveRequestResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditLeaveRequest extends EditRecord
{
    protected static string $resource = LeaveRequestResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return [
            ...$data,
            'duration' => $this->record->hasSpecificTimes() ? 'specific_hours' : 'full_day',
            'date_range' => $this->record->date_range,
            'date_time_range' => $this->record->date_time_range,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->label('Cancel request')
                ->modalHeading('Cancel this time-off request?'),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return [
            ...$data,
            'employee_id' => $this->record->employee_id,
            'status' => $this->record->status,
            'reviewed_by' => $this->record->reviewed_by,
            'review_notes' => $this->record->review_notes,
        ];
    }
}
