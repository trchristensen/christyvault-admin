<?php

namespace App\Filament\Resources\EmployeeResource\Pages;

use App\Filament\Resources\EmployeeResource;
use Filament\Resources\Pages\CreateRecord;
use App\Models\Driver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateEmployee extends CreateRecord
{
    protected static string $resource = EmployeeResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return DB::transaction(function () use ($data) {
            $employee = static::getModel()::create($data);

            if ($employee->position === 'driver' && isset($data['driver'])) {
                $this->createDriverRecord($employee, $data['driver']);
            }

            return $employee;
        });
    }

    protected function createDriverRecord(Model $employee, array $driverData): void
    {
        $driverData['employee_id'] = $employee->id;

        // $driverValidator = validator($driverData, [
        //     'license_number' => 'string',
        //     'license_expiration' => 'date',
        //     // Add any other validation rules for driver fields
        // ]);

        // if ($driverValidator->fails()) {
        //     throw ValidationException::withMessages($driverValidator->errors()->toArray());
        // }

        Driver::create($driverData);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
