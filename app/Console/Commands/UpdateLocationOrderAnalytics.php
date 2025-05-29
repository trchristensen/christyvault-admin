<?php

namespace App\Console\Commands;

use App\Models\Location;
use Illuminate\Console\Command;

class UpdateLocationOrderAnalytics extends Command
{
    protected $signature = 'locations:update-analytics {--location= : Update analytics for a specific location ID}';
    protected $description = 'Update order analytics for locations';

    public function handle()
    {
        $locationId = $this->option('location');

        if ($locationId) {
            $location = Location::find($locationId);
            if (!$location) {
                $this->error("Location with ID {$locationId} not found.");
                return 1;
            }

            $this->info("Updating analytics for location: {$location->name}");
            $location->updateOrderAnalytics();
            $this->info('Done!');
            return 0;
        }

        $locations = Location::all();
        $bar = $this->output->createProgressBar(count($locations));
        $bar->start();

        foreach ($locations as $location) {
            $location->updateOrderAnalytics();
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info('All location analytics have been updated!');
        return 0;
    }
} 