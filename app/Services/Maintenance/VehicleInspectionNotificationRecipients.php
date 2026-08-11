<?php

namespace App\Services\Maintenance;

use App\Enums\PlantLocation;
use App\Models\TripPreTripInspection;
use App\Models\User;
use Illuminate\Support\Collection;

final class VehicleInspectionNotificationRecipients
{
    /** @return Collection<int, User> */
    public function forInspection(TripPreTripInspection $inspection, ?int $exceptUserId = null): Collection
    {
        $plantLocations = $this->plantLocations($inspection);

        $globalRecipients = User::role([
            'admin',
            'super-admin',
            'maintenance-manager',
            'maintenance-technician',
        ])->get();

        $dispatchRecipients = User::permission('manage delivery trip dispatch')
            ->get()
            ->filter(fn (User $user): bool => $this->dispatchRecipientMatches($user, $plantLocations));

        return $globalRecipients
            ->merge($dispatchRecipients)
            ->when($exceptUserId, fn (Collection $users): Collection => $users->reject(
                fn (User $user): bool => $user->getKey() === $exceptUserId,
            ))
            ->unique(fn (User $user): int => $user->getKey())
            ->values();
    }

    /** @return Collection<int, string> */
    private function plantLocations(TripPreTripInspection $inspection): Collection
    {
        $inspection->loadMissing(['trip.orders', 'trip.stops.order', 'assets.location']);

        if ($inspection->trip) {
            return $inspection->trip->orderedDeliveryOrders()
                ->pluck('plant_location')
                ->filter(fn ($plant): bool => PlantLocation::tryFrom((string) $plant) !== null)
                ->map(fn ($plant): string => (string) $plant)
                ->unique()
                ->values();
        }

        return $inspection->assets
            ->map(fn ($asset): ?string => $asset->location?->physicalPlantLocation()?->value)
            ->filter(fn ($plant): bool => PlantLocation::tryFrom((string) $plant) !== null)
            ->unique()
            ->values();
    }

    /** @param Collection<int, string> $plantLocations */
    private function dispatchRecipientMatches(User $user, Collection $plantLocations): bool
    {
        if ($plantLocations->isEmpty()) {
            return false;
        }

        $visibleDeliveryTypes = collect($user->team_schedule_delivery_types ?? [])
            ->filter(fn ($plant): bool => PlantLocation::tryFrom((string) $plant) !== null)
            ->map(fn ($plant): string => (string) $plant)
            ->values();

        return $visibleDeliveryTypes->isEmpty()
            || $visibleDeliveryTypes->intersect($plantLocations)->isNotEmpty();
    }
}
