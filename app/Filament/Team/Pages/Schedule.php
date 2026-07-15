<?php

namespace App\Filament\Team\Pages;

use App\Enums\PlantLocation;
use App\Filament\Team\Concerns\ManagesDeliveryPhotos;
use App\Filament\Team\Concerns\ManagesDeliveryTripDispatch;
use App\Models\CalendarDay;
use App\Models\Order;
use App\Models\Trip;
use Carbon\Carbon;
use Filament\Pages\Page;

class Schedule extends Page
{
    use ManagesDeliveryPhotos;
    use ManagesDeliveryTripDispatch;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-document-text';

    protected string $view = 'filament.team.pages.schedule';

    protected static ?string $title = 'Delivery Schedule';

    public static function canAccess(): bool
    {
        return auth()->user()?->can('view team delivery schedule') ?? false;
    }

    // don't display title on page
    public function getTitle(): string
    {
        return '';
    }


    public array $dates = [];
    public string $selectedDate;
    public $orders;
    public array $selectedCalendarDays = [];

    public function mount()
    {
        $today = Carbon::today();
        $start = $today->copy()->subDays(14);
        $end = $this->scheduleEndDate($today, $this->scheduleDaysAhead());
        $allowedDeliveryTypes = $this->allowedDeliveryTypes();
        $calendarDays = CalendarDay::query()
            ->whereDate('date', '>=', $start->toDateString())
            ->whereDate('date', '<=', $end->toDateString())
            ->orderBy('name')
            ->get()
            ->groupBy(fn(CalendarDay $calendarDay): string => $calendarDay->date->toDateString());
        $deliveryCounts = Order::query()
            ->selectRaw('assigned_delivery_date, plant_location, COUNT(*) as total')
            ->whereDate('assigned_delivery_date', '>=', $start->toDateString())
            ->whereDate('assigned_delivery_date', '<=', $end->toDateString())
            ->whereNotNull('assigned_delivery_date')
            ->when($allowedDeliveryTypes !== [], fn($query) => $query->whereIn('plant_location', $allowedDeliveryTypes))
            ->groupBy('assigned_delivery_date', 'plant_location')
            ->get()
            ->groupBy(fn($row): string => Carbon::parse($row->assigned_delivery_date)->toDateString())
            ->map(fn($rows) => $rows->pluck('total', 'plant_location'));

        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            if ($date->isWeekend()) {
                continue;
            }

            $dateCalendarDays = $calendarDays
                ->get($date->toDateString(), collect())
                ->map(fn(CalendarDay $calendarDay): array => [
                    'name' => $calendarDay->name,
                    'type' => $calendarDay->type,
                    'type_label' => $calendarDay->type_label,
                    'blocks_delivery' => $calendarDay->blocks_delivery,
                    'opens_delivery' => $calendarDay->opens_delivery,
                ])
                ->values()
                ->toArray();
            $dateString = $date->toDateString();
            $dateDeliveryCounts = $deliveryCounts->get($dateString, collect());
            $deliveryMarkers = collect([
                [
                    'key' => 'colma_main',
                    'label' => 'Colma',
                    'count' => (int) ($dateDeliveryCounts['colma_main'] ?? 0),
                    'class' => 'delivery-marker-colma',
                ],
                [
                    'key' => 'colma_locals',
                    'label' => 'Locals',
                    'count' => (int) ($dateDeliveryCounts['colma_locals'] ?? 0),
                    'class' => 'delivery-marker-locals',
                ],
                [
                    'key' => 'tulare_plant',
                    'label' => 'Tulare',
                    'count' => (int) ($dateDeliveryCounts['tulare_plant'] ?? 0),
                    'class' => 'delivery-marker-tulare',
                ],
            ])
                ->filter(fn(array $marker): bool => $marker['count'] > 0)
                ->values()
                ->toArray();

            $this->dates[] = [
                'iso' => $dateString,
                'label' => $this->labelFor($date, $today),
                'weekday' => $date->format('D'),
                'day' => $date->format('j'),
                'month' => $date->format('F Y'),
                'calendar_days' => $dateCalendarDays,
                'blocks_delivery' => collect($dateCalendarDays)->contains('blocks_delivery', true),
                'delivery_markers' => $deliveryMarkers,
            ];
        }

        $initialDate = $today->copy();

        while ($initialDate->isWeekend()) {
            $initialDate->subDay();
        }

        $this->selectedDate = $initialDate->toDateString();
        $this->loadOrdersFor($this->selectedDate);
    }

    protected function labelFor(Carbon $date, Carbon $today): string
    {
        if ($date->isToday()) return 'Today';
        if ($date->isTomorrow()) return 'Tomorrow';
        if ($date->isYesterday()) return 'Yesterday';
        return '';
    }

    public function monthFor(Carbon $date): string
    {
        return $date->format('F Y');
    }

    public function selectDate(string $iso)
    {
        $this->selectedDate = $iso;
        $this->loadOrdersFor($iso);
    }

    protected function deliveryPhotoOrderIsInScope(Order $order): bool
    {
        if (! static::canAccess()) {
            return false;
        }

        if (! $order->assigned_delivery_date || $order->assigned_delivery_date->toDateString() !== $this->selectedDate) {
            return false;
        }

        $allowedDeliveryTypes = $this->allowedDeliveryTypes();

        return $allowedDeliveryTypes === []
            || in_array((string) $order->plant_location, $allowedDeliveryTypes, true);
    }

    protected function refreshDeliveryPhotoView(): void
    {
        $this->loadOrdersFor($this->selectedDate);
    }

    protected function allowedDeliveryTypes(): array
    {
        $types = auth()->user()?->team_schedule_delivery_types ?? [];

        return collect($types)
            ->filter(fn($type): bool => PlantLocation::tryFrom((string) $type) !== null)
            ->values()
            ->toArray();
    }

    protected function scheduleDaysAhead(): int
    {
        $daysAhead = auth()->user()?->team_schedule_days_ahead;

        if ($daysAhead === null || $daysAhead === '') {
            return 14;
        }

        return max(0, min(90, (int) $daysAhead));
    }

    protected function scheduleEndDate(Carbon $startDate, int $visibleWeekdaysAhead): Carbon
    {
        $date = $startDate->copy();
        $weekdaysFound = 0;

        while ($weekdaysFound < $visibleWeekdaysAhead) {
            $date->addDay();

            if ($date->isWeekend()) {
                continue;
            }

            $weekdaysFound++;
        }

        return $date;
    }

    protected function loadOrdersFor(string $iso)
    {
        $this->selectedCalendarDays = CalendarDay::query()
            ->whereDate('date', $iso)
            ->orderByDesc('blocks_delivery')
            ->orderBy('name')
            ->get()
            ->map(fn(CalendarDay $calendarDay): array => [
                'name' => $calendarDay->name,
                'type' => $calendarDay->type,
                'type_label' => $calendarDay->type_label,
                'blocks_delivery' => $calendarDay->blocks_delivery,
                'opens_delivery' => $calendarDay->opens_delivery,
                'notes' => $calendarDay->notes,
            ])
            ->values()
            ->toArray();

        $allowedDeliveryTypes = $this->allowedDeliveryTypes();

        $orders = Order::whereDate('assigned_delivery_date', $iso)
            ->when($allowedDeliveryTypes !== [], fn ($query) => $query->whereIn('plant_location', $allowedDeliveryTypes))
            ->with([
                'location',
                'orderProducts.product',
                'driver',
                'activeTripStop',
                'trip.driver',
                'trip.orders:id,trip_id,plant_location,stop_number',
                'trip.stops.order:id,plant_location',
                'deliveryPhotos.uploadedBy',
            ])
            ->withCount('deliveryPhotos')
            ->get();

        // Define the custom plant order
        $plantOrder = [
            'colma_main' => 1,
            'colma_locals' => 2,
            'tulare_plant' => 3,
        ];

        $effectivePlant = function (Order $order): string {
            $tripOrders = $order->trip && ! $order->trip->trashed()
                ? $order->trip->orderedDeliveryOrders()
                : collect();

            return (string) ($tripOrders->count() > 1
                ? ($tripOrders->sortBy('stop_number')->first()?->plant_location ?? $order->plant_location)
                : $order->plant_location);
        };

        // Keep the stops in a delivery trip next to each other and in stop order.
        $sorted = $orders->sortBy(fn (Order $order): string => sprintf(
            '%03d-%s-%03d-%010d',
            $plantOrder[$effectivePlant($order)] ?? 999,
            $order->trip_id ? 'trip-'.str_pad((string) $order->trip_id, 10, '0', STR_PAD_LEFT) : 'order-'.str_pad((string) $order->id, 10, '0', STR_PAD_LEFT),
            $order->activeTripStop?->sequence ?? $order->stop_number ?? 0,
            $order->id,
        ));

        // A delivery trip belongs under its first stop's plant heading so its stops stay together.
        $this->orders = collect([
            'colma_main' => $sorted->filter(fn (Order $order): bool => $effectivePlant($order) === 'colma_main'),
            'colma_locals' => $sorted->filter(fn (Order $order): bool => $effectivePlant($order) === 'colma_locals'),
            'tulare_plant' => $sorted->filter(fn (Order $order): bool => $effectivePlant($order) === 'tulare_plant'),
        ])->filter(fn ($group) => $group->isNotEmpty());
    }

    protected function deliveryTripDispatchIsInScope(Trip $trip): bool
    {
        if ($trip->scheduled_date?->toDateString() !== $this->selectedDate) {
            return false;
        }

        $allowedDeliveryTypes = $this->allowedDeliveryTypes();

        return $allowedDeliveryTypes === []
            || $trip->orderedDeliveryOrders()->every(fn (Order $order): bool => in_array(
                (string) $order->plant_location,
                $allowedDeliveryTypes,
                true,
            ));
    }

    protected function refreshDeliveryTripDispatchView(): void
    {
        $this->loadOrdersFor($this->selectedDate);
    }
}
