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
        // NOTE: adjust 'requested_delivery_date' and 'scheduled_at' to your real column names if different
        $this->orders = Order::whereDate('assigned_delivery_date', $iso)
            // ->orderBy('scheduled_at')
            ->with(['location', 'orderProducts.product', 'driver'])
            ->orderBy('plant_location') // First by plant location
            ->get();
    }
}
