<?php

namespace App\Filament\Resources\TripResource\Pages;

use App\Filament\Resources\TripResource;
use App\Services\SplitLoadService;
use App\Services\TripVehicleConfigurationResolver;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class EditTrip extends EditRecord
{
    protected static string $resource = TripResource::class;

    public $confirmedDateChange = false;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $orders = $this->record->orders()
            ->orderBy('stop_number')
            ->get();

        $data['vehicle_configuration_id'] ??= app(TripVehicleConfigurationResolver::class)
            ->defaultIdForOrderIds($orders->modelKeys());
        $data['orders'] = $orders
            ->map(fn ($order): array => [
                'order_id' => $order->getKey(),
                'delivery_notes' => $order->delivery_notes,
            ])
            ->all();

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $orders = $data['orders'] ?? [];
        $tripData = Arr::except($data, 'orders');

        return DB::transaction(function () use ($record, $orders, $tripData): Model {
            $trip = app(SplitLoadService::class)->updateTrip(
                $record,
                $orders,
                $tripData['scheduled_date'],
                $tripData['driver_id'] ?? null,
                $tripData['vehicle_configuration_id'] ?? null,
            );

            $trip->update(Arr::except($tripData, [
                'driver_id',
                'scheduled_date',
                'trip_number',
                'vehicle_configuration_id',
            ]));

            return $trip->refresh();
        });
    }

    protected function afterSave(): void
    {
        // If this was a confirmed date change, update the order dates
        if ($this->confirmedDateChange) {
            $this->record->orders()->update([
                'assigned_delivery_date' => $this->record->scheduled_date,
            ]);

            Notification::make()
                ->success()
                ->title('Order Dates Updated')
                ->body('All associated order delivery dates have been updated.')
                ->send();
        }

        $this->confirmedDateChange = false;
    }
}
