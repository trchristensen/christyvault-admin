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
        if ($this->record->positions()->where('name', 'driver')->exists()) {
            $data['driver'] = $this->record->driver?->toArray() ?? [];
        }
        $data['positions'] = $this->record->positions()->pluck('positions.id')->toArray();
        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        // Debug incoming data
        \Log::info('Update Employee - Incoming Data:', [
            'record_id' => $record->id,
            'data' => $data,
        ]);

        return DB::transaction(function () use ($record, $data) {
            // Debug positions before update
            \Log::info('Current Positions:', [
                'employee_id' => $record->id,
                'positions' => $record->positions()->pluck('name')->toArray(),
            ]);

            $wasDriver = $record->positions()->where('name', 'driver')->exists();
            $isDriver = in_array('driver', $data['positions'] ?? []);

            \Log::info('Driver Status:', [
                'wasDriver' => $wasDriver,
                'isDriver' => $isDriver,
                'positions_in_data' => $data['positions'] ?? 'no positions in data',
            ]);

            $record->update($data);

            // Debug after main record update
            \Log::info('After Record Update:', [
                'updated_data' => $record->fresh()->toArray(),
            ]);

            // Sync positions
            $record->positions()->sync($data['positions'] ?? []);

            // Debug after position sync
            \Log::info('After Position Sync:', [
                'new_positions' => $record->fresh()->positions()->pluck('name')->toArray(),
            ]);

            // Handle driver record
            if ($isDriver) {
                \Log::info('Creating/Updating Driver Record:', [
                    'driver_data' => $data['driver'] ?? [],
                ]);
                $this->updateOrCreateDriverRecord($record, $data['driver'] ?? []);
            } elseif ($wasDriver && !$isDriver) {
                \Log::info('Deleting Driver Record');
                $record->driver?->delete();
            }

            return $record->fresh();
        });
    }

    protected function updateOrCreateDriverRecord(Model $employee, array $driverData): void
    {
        $driverData['employee_id'] = $employee->id;

        \Log::info('Driver Record Creation/Update:', [
            'employee_id' => $employee->id,
            'driver_data' => $driverData,
        ]);

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
