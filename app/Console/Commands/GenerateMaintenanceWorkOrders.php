<?php

namespace App\Console\Commands;

use App\Services\Maintenance\MaintenancePlanScheduler;
use Illuminate\Console\Command;

class GenerateMaintenanceWorkOrders extends Command
{
    protected $signature = 'maintenance:generate-work-orders';

    protected $description = 'Generate work orders for due calendar and meter-based maintenance plans';

    public function handle(MaintenancePlanScheduler $scheduler): int
    {
        $count = $scheduler->generateDue();
        $this->info("Generated {$count} maintenance work order(s).");

        return self::SUCCESS;
    }
}
