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
            
            // Sync positions
            if (isset($data['positions'])) {
                $employee->positions()->sync($data['positions']);
            }

            // Create driver record if needed
            if (isset($data['positions']) && in_array('driver', $data['positions']) && isset($data['driver'])) {
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
