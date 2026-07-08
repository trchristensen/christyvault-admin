<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Enums\PlantLocation;
use App\Filament\Resources\OrderResource;
use App\Models\Location;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class CreateOrder extends CreateRecord
{
    protected static string $resource = OrderResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $products = $data['orderProducts'] ?? [];

        return DB::transaction(function () use ($data, $products): Model {
            $order = static::getModel()::create(Arr::except($data, 'orderProducts'));

            if ($products !== []) {
                $order->orderProducts()->createMany($products);
            }

            return $order;
        });
    }

    protected function fillForm(): void
    {
        parent::fillForm();

        $this->prefillLocationFromRequest();
    }

    protected function afterCreate(): void
    {
        $this->record->location?->updateOrderAnalytics();
    }

    protected function prefillLocationFromRequest(): void
    {
        $locationId = request()->integer('location_id');

        if (! $locationId) {
            return;
        }

        $location = Location::query()
            ->with('preferredDeliveryContact')
            ->find($locationId);

        if (! $location) {
            return;
        }

        $prefill = [
            'location_id' => $location->getKey(),
            'plant_location' => $this->defaultPlantLocationFor($location)->value,
        ];

        if ($location->preferredDeliveryContact?->name) {
            $prefill['ordered_by'] = $location->preferredDeliveryContact->name;
        }

        $this->form->fill(array_replace($this->data ?? [], $prefill));
    }

    protected function defaultPlantLocationFor(Location $location): PlantLocation
    {
        if ($location->default_plant_location instanceof PlantLocation) {
            return $location->default_plant_location;
        }

        if ($location->default_plant_location) {
            return PlantLocation::tryFrom((string) $location->default_plant_location) ?? PlantLocation::COLMA_MAIN;
        }

        return in_array(strtolower((string) $location->city), ['colma', 'south san francisco'], true)
            ? PlantLocation::COLMA_LOCALS
            : PlantLocation::COLMA_MAIN;
    }
}
