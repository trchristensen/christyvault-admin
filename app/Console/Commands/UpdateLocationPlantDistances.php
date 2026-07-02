<?php

namespace App\Console\Commands;

use App\Models\Location;
use App\Services\PlantDriveDistanceService;
use Illuminate\Console\Command;
use Throwable;

class UpdateLocationPlantDistances extends Command
{
    protected $signature = 'locations:update-plant-distances
        {--location= : Update a specific location ID}
        {--limit=10 : Maximum locations to update}
        {--stale-days=90 : Recalculate distances older than this many days}
        {--force : Recalculate even when cached values exist}
        {--dry-run : Show what would be updated without calling the routing API}';

    protected $description = 'Calculate cached driving distance from each location to its default plant.';

    public function handle(PlantDriveDistanceService $distanceService): int
    {
        $locationId = $this->option('location');
        $limit = max(1, (int) $this->option('limit'));
        $staleDays = max(1, (int) $this->option('stale-days'));
        $force = (bool) $this->option('force') || filled($locationId);
        $dryRun = (bool) $this->option('dry-run');

        if (! $dryRun && ! $distanceService->isConfigured()) {
            $this->error('OPENROUTESERVICE_API_KEY is not configured.');
            return self::FAILURE;
        }

        $query = Location::query()->orderBy('id');

        if ($locationId) {
            $query->whereKey($locationId);
        } else {
            $query->whereNotNull('latitude')
                ->whereNotNull('longitude');
        }

        if (! $force) {
            $query->where(function ($query) use ($staleDays) {
                $query->whereNull('plant_drive_distance_miles')
                    ->orWhereNull('plant_drive_duration_minutes')
                    ->orWhereNull('plant_drive_distance_calculated_at')
                    ->orWhere('plant_drive_distance_calculated_at', '<', now()->subDays($staleDays));
            });
        }

        $locations = $query->limit($limit)->get();

        if ($locations->isEmpty()) {
            $this->info('No locations need plant distance updates.');
            return self::SUCCESS;
        }

        $updated = 0;
        $skipped = 0;
        $failed = 0;
        $throttleMs = max(0, (int) config('services.openrouteservice.throttle_ms', 1000));

        foreach ($locations as $location) {
            if (! $location->hasCoordinates()) {
                $this->warn("Skipping {$location->id} {$location->name}: missing coordinates.");
                $skipped++;
                continue;
            }

            $origin = $distanceService->originFor($location);

            if (! $origin?->hasCoordinates()) {
                $this->warn("Skipping {$location->id} {$location->name}: no default plant coordinates found.");
                $skipped++;
                continue;
            }

            if ($dryRun) {
                $currentDistance = $location->plant_drive_distance_miles !== null
                    ? "{$location->plant_drive_distance_miles} mi"
                    : 'not calculated';

                $this->line("Would update {$location->id} {$location->name} from {$origin->name}.");
                $this->line("  Coordinates: {$location->latitude}, {$location->longitude}. Current cached distance: {$currentDistance}.");
                $skipped++;
                continue;
            }

            try {
                $distance = $distanceService->distanceFromDefaultPlant($location);

                if (! $distance) {
                    $this->warn("Skipping {$location->id} {$location->name}: missing coordinates.");
                    $skipped++;
                    continue;
                }

                $location->forceFill([
                    'plant_drive_distance_origin_location_id' => $distance['origin_location_id'],
                    'plant_drive_distance_miles' => $distance['distance_miles'],
                    'plant_drive_duration_minutes' => $distance['duration_minutes'],
                    'plant_drive_distance_provider' => $distance['provider'],
                    'plant_drive_distance_calculated_at' => now(),
                ])->save();

                $this->line("Updated {$location->id} {$location->name}: {$distance['distance_miles']} mi, {$distance['duration_minutes']} min.");
                $updated++;

                if ($throttleMs > 0) {
                    usleep($throttleMs * 1000);
                }
            } catch (Throwable $exception) {
                $this->error("Failed {$location->id} {$location->name}: {$exception->getMessage()}");
                $failed++;
            }
        }

        $this->info("Done. Updated: {$updated}. Skipped: {$skipped}. Failed: {$failed}.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
