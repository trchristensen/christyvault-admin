<?php

namespace App\Filament\Resources\EmployeeResource\Pages;

use App\Filament\Resources\EmployeeResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use App\Models\Driver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EditEmployee extends EditRecord
{
    protected static string $resource = EmployeeResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        if ($this->record->position === 'driver') {
            $data['driver'] = $this->record->driver?->toArray() ?? [];
        }
        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return DB::transaction(function () use ($record, $data) {
            $wasDriver = $record->position === 'driver';
            $isDriver = $data['position'] === 'driver';

            $record->update($data);

            if ($isDriver) {
                $this->updateOrCreateDriverRecord($record, $data['driver'] ?? []);
            } elseif ($wasDriver && !$isDriver) {
                $record->driver?->delete();
            }

            return $record;
        });
    }

    protected function updateOrCreateDriverRecord(Model $employee, array $driverData): void
    {
        $driverData['employee_id'] = $employee->id;

        // $driverValidator = validator($driverData, [
        //     'license_number' => 'required|string',
        //     'license_expiration' => 'required|date',
        //     // Add any other validation rules for driver fields
        // ]);

        // if ($driverValidator->fails()) {
        //     throw ValidationException::withMessages($driverValidator->errors()->toArray());
        // }

        Driver::updateOrCreate(
            ['employee_id' => $employee->id],
            $driverData
        );
    }

    protected function getActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
