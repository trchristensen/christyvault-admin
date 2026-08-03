<?php

namespace App\Console\Commands;

use App\Services\Maintenance\MaintenanceFleetPlanScheduler;
use App\Services\Maintenance\MaintenancePlanScheduler;
use Illuminate\Console\Command;

class GenerateMaintenanceWorkOrders extends Command
{
    protected $signature = 'maintenance:generate-work-orders';

    protected $description = 'Generate work orders for due asset and fleet maintenance plans';

    public function handle(MaintenancePlanScheduler $scheduler, MaintenanceFleetPlanScheduler $fleetScheduler): int
    {
        $count = $scheduler->generateDue() + $fleetScheduler->generateDue();
        $this->info("Generated {$count} maintenance work order(s).");

        return self::SUCCESS;
    }
}
