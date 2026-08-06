<?php

namespace App\Filament\Resources\EmployeeResource\Pages;

use App\Filament\Resources\EmployeeResource;
use App\Models\Driver;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class EditEmployee extends EditRecord
{
    protected static string $resource = EmployeeResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        if ($this->record->driver) {
            $data['driver'] = $this->record->driver->toArray();
        }
        $data['positions'] = $this->record->positions()->pluck('positions.id')->toArray();

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return DB::transaction(function () use ($record, $data) {
            $isDriver = EmployeeResource::selectedPositionsIncludeDriver($data['positions'] ?? []);

            $record->update($data);

            // Sync positions
            $record->positions()->sync($data['positions'] ?? []);

            // Keep existing license information even if the employee's position
            // changes. Create a profile only when details are actually supplied.
            if (
                $isDriver &&
                ($record->driver()->exists() || EmployeeResource::hasDriverDetails($data['driver'] ?? []))
            ) {
                $this->updateOrCreateDriverRecord($record, $data['driver'] ?? []);
            }

            return $record->fresh();
        });
    }

    protected function updateOrCreateDriverRecord(Model $employee, array $driverData): void
    {
        $driverData['employee_id'] = $employee->id;

        Driver::updateOrCreate(
            ['employee_id' => $employee->id],
            $driverData
        );
    }

    protected function getActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
