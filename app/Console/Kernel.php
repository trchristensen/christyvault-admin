<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected $commands = [
        Commands\UpdateLocationOrderAnalytics::class,
    ];

    protected function schedule(Schedule $schedule): void
    {
        // $schedule->command('inspire')->hourly();
        
        // Send daily SMS schedules to drivers
        $schedule->command('sms:daily-schedule')
            ->dailyAt(config('sms.daily_schedule.time', '08:00'))
            ->timezone(config('sms.daily_schedule.timezone', config('app.timezone', 'UTC')))
            ->when(function () {
                return config('sms.enabled') && config('sms.daily_schedule.enabled');
            });
    }
}
