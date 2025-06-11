<?php

namespace App\Console\Commands;

use App\Models\Employee;
use App\Services\SmsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendDailySmsToDrivers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sms:daily-schedule {--driver=} {--dry-run}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send daily delivery schedules to drivers via SMS';

    public function __construct(
        private SmsService $smsService
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (!config('sms.enabled') || !config('sms.daily_schedule.enabled')) {
            $this->info('Daily SMS schedule is disabled');
            return self::SUCCESS;
        }

        $this->info('Starting daily SMS delivery...');

        // Get drivers with phone numbers who have deliveries today
        $driversQuery = Employee::whereNotNull('phone')
            ->whereHas('positions', function ($q) {
                $q->where('name', 'driver'); // Adjust this based on your position naming
            })
            ->whereHas('orders', function ($q) {
                $q->whereDate('assigned_delivery_date', today());
            });

        // If specific driver is requested
        if ($driverId = $this->option('driver')) {
            $driversQuery->where('id', $driverId);
        }

        $drivers = $driversQuery->get();

        if ($drivers->isEmpty()) {
            $this->info('No drivers found with deliveries today');
            return self::SUCCESS;
        }

        $this->info("Found {$drivers->count()} drivers with deliveries today");

        $sent = 0;
        $failed = 0;

        foreach ($drivers as $driver) {
            $this->info("Processing driver: {$driver->name} ({$driver->phone})");

            if ($this->option('dry-run')) {
                $this->line("  [DRY RUN] Would send SMS to {$driver->name}");
                $sent++;
                continue;
            }

            try {
                if ($this->smsService->sendDailySchedule($driver)) {
                    $this->info("  ✓ SMS sent successfully");
                    $sent++;
                } else {
                    $this->error("  ✗ Failed to send SMS");
                    $failed++;
                }
            } catch (\Exception $e) {
                $this->error("  ✗ Error: {$e->getMessage()}");
                $failed++;
                
                Log::error('Daily SMS command error', [
                    'driver_id' => $driver->id,
                    'error' => $e->getMessage()
                ]);
            }

            // Small delay to avoid rate limiting
            usleep(500000); // 0.5 seconds
        }

        $this->newLine();
        $this->info("Daily SMS delivery complete!");
        $this->info("  Sent: {$sent}");
        if ($failed > 0) {
            $this->error("  Failed: {$failed}");
        }

        Log::info('Daily SMS command completed', [
            'total_drivers' => $drivers->count(),
            'sent' => $sent,
            'failed' => $failed,
            'dry_run' => $this->option('dry-run')
        ]);

        return self::SUCCESS;
    }
}
