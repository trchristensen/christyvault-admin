<?php

namespace App\Observers;

use App\Models\Location;

class LocationObserver
{
    public function updating(Location $location): void
    {
        if ($location->isDirty(['latitude', 'longitude', 'default_plant_location'])) {
            $location->clearPlantDriveDistance();
        }
    }
}
