<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule): void
    {
        // Run backup daily at 2 AM
        $schedule->command('backup:run')->dailyAt('02:00');

        // Run cleanup daily at 3 AM
        $schedule->command('backup:clean')->dailyAt('03:00');

        // Optional: Monitor backup health daily
        $schedule->command('backup:monitor')->dailyAt('04:00');
    }
}
