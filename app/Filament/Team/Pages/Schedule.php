<?php

namespace App\Filament\Team\Pages;

use Carbon\Carbon;
use Filament\Pages\Page;
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

    public function mount()
    {
        $today = Carbon::today();
        $start = $today->copy()->subDays(14);

        for ($i = 0; $i <= 28; $i++) {
            $date = $start->copy()->addDays($i);
            $this->dates[] = [
                'iso' => $date->toDateString(),
                'label' => $this->labelFor($date, $today),
                'weekday' => $date->format('D'),
                'day' => $date->format('j'),
                'month' => $date->format('F Y'),
            ];
        }

        $this->selectedDate = $today->toDateString();
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
