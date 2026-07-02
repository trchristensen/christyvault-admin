<?php

namespace App\Console\Commands;

use App\Models\Location;
use App\Services\LocationGeocodingService;
use Illuminate\Console\Command;
use Throwable;

class GeocodeLocations extends Command
{
    protected $signature = 'locations:geocode
        {--location= : Geocode a specific location ID}
        {--limit=10 : Maximum locations to geocode}
        {--force : Re-geocode even when coordinates already exist}
        {--retry-after-hours=24 : Skip failed geocoding attempts newer than this many hours}
        {--dry-run : Show what would be geocoded without calling the geocoder}';

    protected $description = 'Fill missing location coordinates from the U.S. Census Geocoder.';

    public function handle(LocationGeocodingService $geocodingService): int
    {
        $locationId = $this->option('location');
        $limit = max(1, (int) $this->option('limit'));
        $force = (bool) $this->option('force') || filled($locationId);
        $retryAfterHours = max(1, (int) $this->option('retry-after-hours'));
        $dryRun = (bool) $this->option('dry-run');

        $query = Location::query()
            ->whereNotNull('address_line1')
            ->whereNotNull('city')
            ->whereNotNull('state')
            ->orderBy('id');

        if ($locationId) {
            $query->whereKey($locationId);
        } elseif (! $force) {
            $query->where(function ($query) use ($retryAfterHours) {
                $query->whereNull('latitude')
                    ->orWhereNull('longitude');
            })
                ->where(function ($query) use ($retryAfterHours) {
                    $query->whereNull('geocoding_failed_at')
                        ->orWhere('geocoding_failed_at', '<', now()->subHours($retryAfterHours));
                });
        }

        $locations = $query->limit($limit)->get();

        if ($locations->isEmpty()) {
            $this->info('No locations need geocoding.');
            return self::SUCCESS;
        }

        $updated = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($locations as $location) {
            if ($dryRun) {
                $this->line("Would geocode {$location->id} {$location->name}: {$location->full_address}");
                $skipped++;
                continue;
            }

            try {
                $result = $geocodingService->geocodeLocation($location);

                if (! $result) {
                    $location->forceFill([
                        'geocoding_failed_at' => now(),
                        'geocoding_failure_reason' => 'No Census geocoder match found.',
                    ])->saveQuietly();

                    $this->warn("No match for {$location->id} {$location->name}.");
                    $failed++;
                    continue;
                }

                $location->clearPlantDriveDistance();

                $location->forceFill([
                    'latitude' => $result['latitude'],
                    'longitude' => $result['longitude'],
                    'geocoding_provider' => $result['provider'],
                    'geocoding_matched_address' => $result['matched_address'],
                    'geocoded_at' => now(),
                    'geocoding_failed_at' => null,
                    'geocoding_failure_reason' => null,
                ])->saveQuietly();

                $this->line("Geocoded {$location->id} {$location->name}: {$result['latitude']}, {$result['longitude']}.");
                $updated++;
            } catch (Throwable $exception) {
                $location->forceFill([
                    'geocoding_failed_at' => now(),
                    'geocoding_failure_reason' => str($exception->getMessage())->limit(255)->toString(),
                ])->saveQuietly();

                $this->error("Failed {$location->id} {$location->name}: {$exception->getMessage()}");
                $failed++;
            }
        }

        $this->info("Done. Updated: {$updated}. Skipped: {$skipped}. Failed: {$failed}.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
