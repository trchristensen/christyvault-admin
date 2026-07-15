<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\Trip;
use App\Services\DeliveryTripService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

class BackfillDeliveryTrips extends Command
{
    protected $signature = 'delivery-trips:backfill
        {--apply : Write changes. Without this flag the command is a dry run.}
        {--include-single-stop-trips : Create trips for eligible scheduled orders that do not have one.}
        {--from= : Required with --include-single-stop-trips. Only include deliveries scheduled on or after this date.}
        {--chunk=200 : Number of records to process at a time.}';

    protected $description = 'Safely backfill trip stops and optionally create one-stop trips for scheduled deliveries';

    public function handle(DeliveryTripService $deliveryTrips): int
    {
        $apply = (bool) $this->option('apply');
        $includeSingleStopTrips = (bool) $this->option('include-single-stop-trips');
        $chunkSize = max(1, (int) $this->option('chunk'));
        $fromOption = $this->option('from');

        if (filled($fromOption) && validator(
            ['from' => $fromOption],
            ['from' => ['date_format:Y-m-d']],
        )->fails()) {
            $this->error('--from must use YYYY-MM-DD format. No records were changed.');

            return self::INVALID;
        }

        $from = filled($fromOption) ? Carbon::parse((string) $fromOption)->toDateString() : null;

        if ($includeSingleStopTrips && $from === null) {
            $this->error('--from=YYYY-MM-DD is required with --include-single-stop-trips. No records were changed.');

            return self::INVALID;
        }

        $tripsWithOrders = Trip::withTrashed()->has('orders')->count();
        $missingStopRows = Order::query()
            ->whereNotNull('trip_id')
            ->whereDoesntHave('tripStops', function (Builder $query): void {
                $query->whereColumn('trip_stops.trip_id', 'orders.trip_id')
                    ->whereNull('trip_stops.removed_at');
            })
            ->count();
        $singleStopCandidates = $this->singleStopCandidates($from)->count();

        $this->table(['Check', 'Records'], [
            ['Trips containing legacy orders (including archived)', $tripsWithOrders],
            ['Legacy trip orders missing an active trip_stop', $missingStopRows],
            [$from
                ? "Eligible standalone deliveries scheduled on/after {$from}"
                : 'All eligible standalone deliveries (informational only)', $singleStopCandidates],
        ]);

        if (! $apply) {
            $this->warn('DRY RUN ONLY: no records were changed.');
            $this->line('Use --apply to backfill existing trip stops.');
            $this->line('Singleton conversion requires both --include-single-stop-trips and an explicit --from date.');

            return self::SUCCESS;
        }

        $syncedTrips = 0;
        Trip::withTrashed()
            ->has('orders')
            ->orderBy('id')
            ->chunkById($chunkSize, function ($trips) use ($deliveryTrips, &$syncedTrips): void {
                foreach ($trips as $trip) {
                    $deliveryTrips->syncStopsFromLegacyOrders($trip);
                    $syncedTrips++;
                }
            });

        $createdTrips = 0;

        if ($includeSingleStopTrips) {
            $this->singleStopCandidates($from)
                ->orderBy('id')
                ->chunkById($chunkSize, function ($orders) use ($deliveryTrips, &$createdTrips): void {
                    foreach ($orders as $order) {
                        if ($deliveryTrips->ensureScheduledOrderHasTrip($order)) {
                            $createdTrips++;
                        }
                    }
                });
        }

        $this->info("Backfilled stops for {$syncedTrips} existing trips.");
        $this->info("Created {$createdTrips} one-stop trips.");
        $this->info('No order or trip records were deleted.');

        return self::SUCCESS;
    }

    private function singleStopCandidates(?string $from = null): Builder
    {
        return Order::query()
            ->whereNull('trip_id')
            ->whereNotNull('assigned_delivery_date')
            ->when($from, fn (Builder $query) => $query->whereDate('assigned_delivery_date', '>=', $from))
            ->whereNotIn('status', DeliveryTripService::nonRouteStatuses());
    }
}
