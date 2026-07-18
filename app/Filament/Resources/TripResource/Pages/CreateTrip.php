<?php

namespace App\Filament\Resources\TripResource\Pages;

use App\Filament\Resources\TripResource;
use App\Services\SplitLoadService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class CreateTrip extends CreateRecord
{
    protected static string $resource = TripResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $orders = $data['orders'] ?? [];
        $tripData = Arr::except($data, 'orders');

        return DB::transaction(function () use ($orders, $tripData): Model {
            $trip = app(SplitLoadService::class)->createTrip(
                $orders,
                $tripData['scheduled_date'],
                $tripData['driver_id'] ?? null,
            );

            $trip->update(Arr::except($tripData, [
                'driver_id',
                'scheduled_date',
                'trip_number',
            ]));

            return $trip->refresh();
        });
    }
}
