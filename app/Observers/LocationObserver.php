<?php

namespace App\Observers;

use App\Models\Location;
use App\Services\LocationGeocodingService;
use Throwable;

class LocationObserver
{
    public function saving(Location $location): void
    {
        if ($this->shouldGeocode($location)) {
            $this->geocode($location);
        }
    }

    public function updating(Location $location): void
    {
        if ($location->isDirty(['latitude', 'longitude', 'default_plant_location']) || $location->addressFieldsChanged()) {
            $location->clearPlantDriveDistance();
        }
    }

    protected function shouldGeocode(Location $location): bool
    {
        if (! $location->hasAddressForGeocoding()) {
            return false;
        }

        if ($location->isDirty(['latitude', 'longitude'])) {
            return false;
        }

        return ! $location->exists
            || ! $location->hasCoordinates()
            || $location->addressFieldsChanged();
    }

    protected function geocode(Location $location): void
    {
        try {
            $result = app(LocationGeocodingService::class)->geocodeLocation($location);

            if (! $result) {
                $location->geocoding_failed_at = now();
                $location->geocoding_failure_reason = 'No Census geocoder match found.';

                if ($location->exists && $location->addressFieldsChanged()) {
                    $location->latitude = null;
                    $location->longitude = null;
                    $location->geocoded_at = null;
                    $location->geocoding_provider = null;
                    $location->geocoding_matched_address = null;
                }

                return;
            }

            $location->latitude = $result['latitude'];
            $location->longitude = $result['longitude'];
            $location->geocoding_provider = $result['provider'];
            $location->geocoding_matched_address = $result['matched_address'];
            $location->geocoded_at = now();
            $location->geocoding_failed_at = null;
            $location->geocoding_failure_reason = null;
        } catch (Throwable $exception) {
            $location->geocoding_failed_at = now();
            $location->geocoding_failure_reason = str($exception->getMessage())->limit(255)->toString();

            if ($location->exists && $location->addressFieldsChanged()) {
                $location->latitude = null;
                $location->longitude = null;
                $location->geocoded_at = null;
                $location->geocoding_provider = null;
                $location->geocoding_matched_address = null;
            }
        }
    }
}
