<?php

namespace App\Filament\Team\Pages;

use Carbon\Carbon;
use Filament\Pages\Page;
use App\Models\CalendarDay;
use App\Models\Order;

class Schedule extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static string $view = 'filament.team.pages.schedule';

    protected static ?string $title = 'Delivery Schedule';

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
        $end = $start->copy()->addDays(28);
        $calendarDays = CalendarDay::query()
            ->whereDate('date', '>=', $start->toDateString())
            ->whereDate('date', '<=', $end->toDateString())
            ->orderBy('name')
            ->get()
            ->groupBy(fn(CalendarDay $calendarDay): string => $calendarDay->date->toDateString());
        $deliveryCounts = Order::query()
            ->selectRaw('assigned_delivery_date, COUNT(*) as total')
            ->whereDate('assigned_delivery_date', '>=', $start->toDateString())
            ->whereDate('assigned_delivery_date', '<=', $end->toDateString())
            ->whereNotNull('assigned_delivery_date')
            ->groupBy('assigned_delivery_date')
            ->pluck('total', 'assigned_delivery_date');

        for ($i = 0; $i <= 28; $i++) {
            $date = $start->copy()->addDays($i);

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

            $this->dates[] = [
                'iso' => $date->toDateString(),
                'label' => $this->labelFor($date, $today),
                'weekday' => $date->format('D'),
                'day' => $date->format('j'),
                'month' => $date->format('F Y'),
                'calendar_days' => $dateCalendarDays,
                'blocks_delivery' => collect($dateCalendarDays)->contains('blocks_delivery', true),
                'delivery_count' => (int) ($deliveryCounts[$date->toDateString()] ?? 0),
            ];
        }

        $initialDate = $today->copy();

        while ($initialDate->isWeekend()) {
            $initialDate->addDay();
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

        $orders = Order::whereDate('assigned_delivery_date', $iso)
            ->with(['location', 'orderProducts.product', 'driver'])
            ->get();

        // Define the custom plant order
        $plantOrder = [
            'colma_main' => 1,
            'colma_locals' => 2,
            'tulare_plant' => 3,
        ];

        // Sort orders by plant order
        $sorted = $orders->sortBy(fn($order) => $plantOrder[$order->plant_location] ?? 999);

        // Group by plant_location safely for Blade
        $this->orders = collect([
            'colma_main' => $sorted->where('plant_location', 'colma_main'),
            'colma_locals' => $sorted->where('plant_location', 'colma_locals'),
            'tulare_plant' => $sorted->where('plant_location', 'tulare_plant'),
        ])->filter(fn($group) => $group->isNotEmpty());
    }
}
