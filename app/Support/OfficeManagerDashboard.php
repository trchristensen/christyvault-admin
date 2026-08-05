<?php

namespace App\Support;

use App\Enums\OrderStatus;
use App\Enums\PlantLocation;
use App\Filament\Resources\LeaveRequestResource;
use App\Filament\Resources\OrderResource;
use App\Filament\Resources\TripResource;
use App\Models\CalendarDay;
use App\Models\LeaveRequest;
use App\Models\Location;
use App\Models\Order;
use App\Models\Trip;
use App\Services\DeliveryCalendarAvailability;
use App\Services\LoadPlanning\TripLoadPlanService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Throwable;

final class OfficeManagerDashboard
{
    public function __construct(
        private readonly DeliveryCalendarAvailability $availability,
        private readonly TripLoadPlanService $loadPlanService,
    ) {}

    public function attentionItems(): array
    {
        $today = today();
        $nextWorkday = $this->nextWorkday($today);
        $items = collect();
        $approvedLeave = LeaveRequest::query()
            ->where('status', 'approved')
            ->where('start_date', '<=', $nextWorkday->copy()->endOfDay())
            ->where('end_date', '>=', $today->copy()->startOfDay())
            ->get()
            ->groupBy('employee_id');

        $trips = Trip::query()
            ->with(['driver', 'orders.location', 'stops.order.location', 'vehicleConfiguration'])
            ->whereBetween('scheduled_date', [$today, $nextWorkday])
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->orderBy('scheduled_date')
            ->get();

        foreach ($trips as $trip) {
            $issues = $this->tripIssues($trip, $approvedLeave);

            if ($issues === []) {
                continue;
            }

            $orders = $trip->orderedDeliveryOrders();
            $locations = $orders->pluck('location.name')->filter()->unique()->values();
            $plants = $orders
                ->map(fn (Order $order): ?string => PlantLocation::tryFrom((string) $order->plant_location)?->getLabel())
                ->filter()
                ->unique()
                ->values();
            $destinationSummary = $locations->take(2)->join(' + ');

            if ($locations->count() > 2) {
                $destinationSummary .= ' +'.($locations->count() - 2).' more';
            }

            $items->push([
                'tone' => collect($issues)->contains(fn (array $issue): bool => $issue['urgent']) ? 'danger' : 'warning',
                'icon' => 'heroicon-o-truck',
                'eyebrow' => $trip->scheduled_date->isToday() ? 'Today' : $trip->scheduled_date->format('l, M j'),
                'title' => $trip->trip_number.($destinationSummary ? " · {$destinationSummary}" : ''),
                'detail' => collect($issues)->pluck('label')->join(' · '),
                'summary' => collect([
                    trans_choice(':count order|:count orders', $orders->count(), ['count' => $orders->count()]),
                    $plants->join(' + '),
                    $trip->driver?->name ?? 'Driver unassigned',
                    $trip->vehicleConfiguration?->name ?? 'Vehicle not selected',
                ])->filter()->join(' · '),
                'orders' => $this->orderDetails($orders),
                'more_orders' => max(0, $orders->count() - 4),
                'action' => 'Review trip',
                'url' => TripResource::getUrl('edit', ['record' => $trip]),
            ]);
        }

        foreach ([$today, $nextWorkday] as $date) {
            $standaloneOrders = $this->deliveryOrders()
                ->with('location')
                ->whereDate('assigned_delivery_date', $date)
                ->whereNull('trip_id')
                ->latest()
                ->get();
            $standalone = $standaloneOrders->count();

            if ($standalone > 0) {
                $items->push([
                    'tone' => 'warning',
                    'icon' => 'heroicon-o-calendar-days',
                    'eyebrow' => $date->isToday() ? 'Today' : $date->format('l, M j'),
                    'title' => trans_choice(':count scheduled order needs a trip|:count scheduled orders need trips', $standalone, ['count' => $standalone]),
                    'detail' => 'These are delivery orders, not Will Call or carrier shipments.',
                    'orders' => $this->orderDetails($standaloneOrders),
                    'more_orders' => max(0, $standalone - 4),
                    'action' => 'Open schedule',
                    'url' => OrderResource::getUrl('calendar', ['date' => $date->toDateString()]),
                ]);
            }
        }

        $unscheduledOrderRecords = $this->deliveryOrders()
            ->with('location')
            ->whereNull('assigned_delivery_date')
            ->latest()
            ->get();
        $unscheduledOrders = $unscheduledOrderRecords->count();

        if ($unscheduledOrders > 0) {
            $items->push([
                'tone' => 'warning',
                'icon' => 'heroicon-o-inbox-stack',
                'eyebrow' => 'Scheduling',
                'title' => trans_choice(':count delivery order needs a date|:count delivery orders need dates', $unscheduledOrders, ['count' => $unscheduledOrders]),
                'detail' => 'Active delivery orders that have not been placed on the calendar.',
                'orders' => $this->orderDetails($unscheduledOrderRecords),
                'more_orders' => max(0, $unscheduledOrders - 4),
                'action' => 'Review orders',
                'url' => OrderResource::getUrl('index'),
            ]);
        }

        $pendingLeave = LeaveRequest::query()->where('status', 'pending')->count();

        if ($pendingLeave > 0) {
            $items->push([
                'tone' => 'info',
                'icon' => 'heroicon-o-user-minus',
                'eyebrow' => 'Employees',
                'title' => trans_choice(':count time-off request is waiting|:count time-off requests are waiting', $pendingLeave, ['count' => $pendingLeave]),
                'detail' => 'Review the dates and staffing impact before responding.',
                'action' => 'Review requests',
                'url' => LeaveRequestResource::getUrl('index'),
            ]);
        }

        return $items
            ->sortBy(fn (array $item): int => match ($item['tone']) {
                'danger' => 1,
                'warning' => 2,
                default => 3,
            })
            ->take(8)
            ->values()
            ->all();
    }

    public function dayBriefings(): array
    {
        $dates = collect([today(), $this->nextWorkday(today())])
            ->unique(fn (Carbon $date): string => $date->toDateString());

        return $dates->map(function (Carbon $date, int $index): array {
            $trips = Trip::query()
                ->with(['driver', 'orders.location', 'stops.order.location', 'vehicleConfiguration'])
                ->whereDate('scheduled_date', $date)
                ->whereNotIn('status', ['completed', 'cancelled'])
                ->orderBy('start_time')
                ->orderBy('id')
                ->get()
                ->map(function (Trip $trip): array {
                    $issues = $this->tripIssues($trip);
                    $locations = $trip->orderedDeliveryOrders()
                        ->pluck('location.name')
                        ->filter()
                        ->unique()
                        ->values();

                    return [
                        'number' => $trip->trip_number,
                        'driver' => $trip->driver?->name ?? 'Driver unassigned',
                        'vehicle' => $trip->vehicleConfiguration?->name ?? 'Vehicle not selected',
                        'stops' => $trip->deliveryStopCount(),
                        'locations' => $locations->take(3)->join(' · '),
                        'more_locations' => max(0, $locations->count() - 3),
                        'ready' => $issues === [],
                        'issues' => collect($issues)->pluck('label')->all(),
                        'url' => TripResource::getUrl('edit', ['record' => $trip]),
                        'load_url' => $trip->loadSummaryIsVisibleTo(auth()->user())
                            ? route('trips.load-summary.print', ['trip' => $trip])
                            : null,
                    ];
                });

            $absences = LeaveRequest::query()
                ->with('employee.positions')
                ->where('status', 'approved')
                ->where('start_date', '<=', $date->copy()->endOfDay())
                ->where('end_date', '>=', $date->copy()->startOfDay())
                ->get()
                ->map(fn (LeaveRequest $leave): array => [
                    'employee' => $leave->employee?->name ?? 'Unknown employee',
                    'positions' => $leave->employee?->positions->pluck('display_name')->filter()->join(', ')
                        ?: $leave->employee?->positions->pluck('name')->map(fn ($name) => str($name)->headline())->join(', '),
                    'type' => str($leave->type)->headline()->toString(),
                ]);

            $standaloneOrders = $this->deliveryOrders()
                ->whereDate('assigned_delivery_date', $date)
                ->whereNull('trip_id')
                ->count();

            return [
                'label' => $index === 0 ? 'Today' : 'Next workday',
                'date' => $date->copy(),
                'blocked' => $this->availability->isBlocked($date),
                'blocking_reason' => $this->availability->blockingReason($date),
                'trips' => $trips,
                'absences' => $absences,
                'standalone_orders' => $standaloneOrders,
                'calendar_url' => OrderResource::getUrl('calendar', ['date' => $date->toDateString()]),
            ];
        })->values()->all();
    }

    public function upcomingCalendarDaysThisWeek(): array
    {
        return $this->availability
            ->calendarDaysForRange(today(), today()->endOfWeek())
            ->map(fn (CalendarDay $day): array => [
                'date' => $day->date->copy(),
                'name' => $day->name,
                'type' => $day->type_label,
                'notes' => $day->notes,
                'tone' => $day->blocks_delivery ? 'danger' : ($day->opens_delivery ? 'success' : 'info'),
                'status' => $day->blocks_delivery ? 'Deliveries blocked' : ($day->opens_delivery ? 'Deliveries open' : 'Calendar note'),
                'url' => OrderResource::getUrl('calendar', ['date' => $day->date->toDateString()]),
            ])
            ->all();
    }

    public function planningOpportunities(): array
    {
        $opportunities = collect($this->loadOpportunities());

        foreach ($this->upcomingWorkdays(7) as $date) {
            foreach ([PlantLocation::COLMA_MAIN, PlantLocation::TULARE_PLANT] as $plant) {
                $hasWork = $this->deliveryOrders()
                    ->whereDate('assigned_delivery_date', $date)
                    ->where('plant_location', $plant->value)
                    ->exists();

                if ($hasWork) {
                    continue;
                }

                $candidates = $this->candidateLocations($plant, $date);

                $opportunities->push([
                    'type' => 'Schedule gap',
                    'tone' => 'primary',
                    'icon' => 'heroicon-o-calendar-days',
                    'title' => $date->format('l, M j').' · No '.$plant->getLabel().' run planned',
                    'detail' => 'No delivery work is currently scheduled for this lane. Staffing capacity is not assumed.',
                    'metrics' => [],
                    'diagram' => null,
                    'candidates' => $candidates,
                    'action' => 'Open calendar',
                    'url' => OrderResource::getUrl('calendar', ['date' => $date->toDateString()]),
                ]);

                if ($opportunities->count() >= 5) {
                    break 2;
                }
            }
        }

        return $opportunities->take(5)->values()->all();
    }

    public function nextWorkday(Carbon $after): Carbon
    {
        $date = $after->copy()->addDay()->startOfDay();

        while ($this->availability->isBlocked($date)) {
            $date->addDay();
        }

        return $date;
    }

    private function deliveryOrders()
    {
        return Order::query()->whereIn('status', $this->deliveryStatuses());
    }

    private function orderDetails(Collection $orders): array
    {
        return $orders->take(4)->map(fn (Order $order): array => [
            'number' => $order->order_number ?? 'Order #'.$order->getKey(),
            'location' => $order->location?->name ?? 'Location not selected',
            'status' => OrderStatus::tryFrom((string) $order->status)?->label()
                ?? str($order->status)->headline()->toString(),
            'customer_order_number' => $order->customer_order_number,
            'plant' => PlantLocation::tryFrom((string) $order->plant_location)?->getLabel(),
            'url' => OrderResource::getUrl('edit', ['record' => $order]),
        ])->values()->all();
    }

    private function tripIssues(Trip $trip, ?Collection $approvedLeave = null): array
    {
        $issues = [];

        if (! $trip->driver_id) {
            $issues[] = ['label' => 'Driver missing', 'urgent' => true];
        } elseif ($approvedLeave?->get($trip->driver_id)?->contains(
            fn (LeaveRequest $leave): bool => $leave->start_date->startOfDay()->lte($trip->scheduled_date)
                && $leave->end_date->endOfDay()->gte($trip->scheduled_date),
        )) {
            $issues[] = ['label' => 'Assigned driver is approved off', 'urgent' => true];
        }

        if (! $trip->vehicle_configuration_id) {
            $issues[] = ['label' => 'Vehicle missing', 'urgent' => true];
        }

        if (! $trip->isStopOrderConfirmed()) {
            $issues[] = ['label' => 'Stop order not confirmed', 'urgent' => false];
        }

        $unprinted = $trip->orders
            ->where('is_printed', false)
            ->reject(fn (Order $order): bool => $order->plant_location === PlantLocation::TULARE_PLANT->value)
            ->count();

        if ($unprinted > 0) {
            $issues[] = [
                'label' => trans_choice(':count tag not printed|:count tags not printed', $unprinted, ['count' => $unprinted]),
                'urgent' => $trip->scheduled_date->lte($this->nextWorkday(today())),
            ];
        }

        return $issues;
    }

    private function loadOpportunities(): array
    {
        return Trip::query()
            ->with(['driver', 'orders.location', 'orders.orderProducts.product.loadingProfile', 'stops.order', 'vehicleConfiguration'])
            ->whereBetween('scheduled_date', [today(), today()->addDays(7)])
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->whereNotNull('vehicle_configuration_id')
            ->orderBy('scheduled_date')
            ->limit(6)
            ->get()
            ->map(function (Trip $trip): ?array {
                if (! $trip->loadSummaryIsVisibleTo(auth()->user())) {
                    return null;
                }

                try {
                    $plan = $this->loadPlanService->forTrip($trip);
                } catch (Throwable) {
                    return null;
                }

                $demand = $plan['demand'];
                $diagram = $plan['diagram'];
                $remainingWeight = (float) ($demand->summary['remaining_product_weight_lbs'] ?? 0);
                $openRackSpots = max(0, (int) ($diagram['rack_spot_count'] ?? 0) - (int) ($diagram['used_rack_spots'] ?? 0));
                $fits = $demand->isReadyForAutomaticPlacement()
                    && ($diagram['available'] ?? false)
                    && ($diagram['unplaced'] ?? []) === [];

                if (! $fits || ($remainingWeight <= 0 && $openRackSpots <= 0)) {
                    return null;
                }

                $plant = PlantLocation::tryFrom((string) ($trip->orderedDeliveryOrders()->first()?->plant_location));

                return [
                    'type' => 'Load opportunity',
                    'tone' => 'success',
                    'icon' => 'heroicon-o-cube-transparent',
                    'title' => $trip->scheduled_date->format('l, M j')." · {$trip->trip_number} may have room",
                    'detail' => trans_choice(':count stop|:count stops', $trip->deliveryStopCount(), ['count' => $trip->deliveryStopCount()]).' · Review the load diagram before promising additional product.',
                    'metrics' => array_values(array_filter([
                        $openRackSpots > 0 ? trans_choice(':count rack spot open|:count rack spots open', $openRackSpots, ['count' => $openRackSpots]) : null,
                        $remainingWeight > 0 ? number_format($remainingWeight, 0).' lb remaining' : null,
                    ])),
                    'diagram' => $diagram,
                    'candidates' => $plant ? $this->candidateLocations($plant, $trip->scheduled_date) : [],
                    'action' => 'Load summary',
                    'url' => route('trips.load-summary.print', ['trip' => $trip]),
                ];
            })
            ->filter()
            ->take(2)
            ->values()
            ->all();
    }

    private function candidateLocations(PlantLocation $plant, Carbon $targetDate): array
    {
        $expectedOrderDate = "locations.last_order_at + (locations.average_order_frequency_days * INTERVAL '1 day')";

        return Location::query()
            ->where('location_type', '!=', 'christy_vault')
            ->where('default_plant_location', $plant->value)
            ->where('total_orders', '>=', 3)
            ->whereNotNull('last_order_at')
            ->where('last_order_at', '>=', today()->subYear())
            ->where('average_order_frequency_days', '>', 0)
            ->whereRaw("{$expectedOrderDate} <= ?", [$targetDate->copy()->addDays(7)->endOfDay()])
            ->whereDoesntHave('orders', fn ($query) => $query->whereIn('status', $this->deliveryStatuses()))
            ->orderByRaw("{$expectedOrderDate} ASC")
            ->limit(3)
            ->get()
            ->map(fn (Location $location): array => [
                'name' => $location->name,
                'timing' => $this->reorderTiming($location),
                'url' => OrderResource::getUrl('create', ['location_id' => $location]),
            ])
            ->all();
    }

    private function reorderTiming(Location $location): string
    {
        $expectedOrderDate = $location->last_order_at
            ?->copy()
            ->startOfDay()
            ->addDays((int) $location->average_order_frequency_days);

        if (! $expectedOrderDate) {
            return 'Cadence unavailable';
        }

        if ($expectedOrderDate->isToday()) {
            return 'Due today';
        }

        $days = (int) round(abs($expectedOrderDate->diffInDays(today())));

        return $expectedOrderDate->isBefore(today())
            ? trans_choice(':count day overdue|:count days overdue', $days, ['count' => $days])
            : trans_choice('Due in :count day|Due in :count days', $days, ['count' => $days]);
    }

    private function upcomingWorkdays(int $count): array
    {
        $dates = [];
        $date = today();

        while (count($dates) < $count) {
            $date = $this->nextWorkday($date);
            $dates[] = $date->copy();
        }

        return $dates;
    }

    private function deliveryStatuses(): array
    {
        return [
            OrderStatus::PENDING->value,
            OrderStatus::CONFIRMED->value,
            OrderStatus::IN_PRODUCTION->value,
            OrderStatus::READY_FOR_DELIVERY->value,
            OrderStatus::OUT_FOR_DELIVERY->value,
            OrderStatus::PREBURY->value,
        ];
    }
}
