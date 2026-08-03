<?php

namespace App\Observers;

use App\Models\MaintenanceMeterReading;
use App\Services\Maintenance\MaintenancePlanScheduler;

class MaintenanceMeterReadingObserver
{
    public function created(MaintenanceMeterReading $reading): void
    {
        $asset = $reading->asset;

        if ($asset && ($asset->current_meter === null || (float) $reading->reading >= (float) $asset->current_meter)) {
            $asset->update([
                'current_meter' => $reading->reading,
                'meter_updated_at' => $reading->recorded_at,
            ]);
        }

        app(MaintenancePlanScheduler::class)->generateDue($reading->asset_id);
    }
}
